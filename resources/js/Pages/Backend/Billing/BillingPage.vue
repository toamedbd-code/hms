<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import axios from 'axios';
import InputLabel from "@/Components/InputLabel.vue";
import { Head } from "@inertiajs/vue3";
import PatientModal from "@/Components/PatientModal.vue";
import { displayResponse, displayWarning, successMessage } from "@/responseMessage.js";
import { debounce } from 'lodash';
import { parse, format, isValid, differenceInYears, differenceInMonths, differenceInDays, subYears, subMonths, subDays } from 'date-fns';

// Silence console output for this module to avoid noisy logs during billing UI
const console = { log: () => {}, warn: () => {}, error: () => {} };

const props = defineProps({
  pageTitle: String,
  pathologyAndRadiologyTests: {
    type: Array,
    default: () => [],
  },
  hospitalCharges: {
    type: Array,
    default: () => [],
  },
  medicineInventories: {
    type: Array,
    default: () => [],
  },
  doctors: {
    type: Array,
    default: () => [],
  },
  patients: {
    type: Array,
    default: () => [],
  },
  id: [String, Number],
  editData: {
    type: Object,
    default: () => ({}),
  },
  referrers: {
    type: Array,
    default: () => [],
  },
  authInfo: {
    type: Object,
    default: () => ({}),
  },
  billing: {
    type: Object,
    default: () => ({}),
  },
  billingDoctors: {
    type: Array,
    default: () => [],
  },
});

const page = usePage();
const unitCompanyName = computed(() => (page.props.websetting ?? page.props.webSetting)?.company_name || 'ToaMed.');
const hasOpenCashCounterSession = computed(() => String(page.props?.cashCounterInfo?.session?.status || '').toLowerCase() === 'open');
const activeCashCounterSessionId = computed(() => Number(page.props?.cashCounterInfo?.session?.id || 0) || null);
const cashCounterStartBusy = ref(false);
const showCounterStartModal = ref(false);
const counterStartForm = ref({
  openingAmount: '',
  note: 'Carry forward from previous shift',
});
const cashCounterCloseBusy = ref(false);
const showCounterCloseModal = ref(false);
const counterCloseForm = ref({
  closingAmount: '',
  note: 'Closed from Billing page quick action',
});
const cashCounterPrintUrl = computed(() => page.props.flash?.cashCounterPrintUrl || '');
const websetting = computed(() => page.props.websetting ?? page.props.webSetting ?? {});
const maxBillingDiscountPercent = computed(() => {
  const raw = Number(websetting.value?.max_billing_discount_percent ?? 100);
  if (!Number.isFinite(raw)) return 100;
  return Math.min(100, Math.max(0, raw));
});
const billingVatEnabled = computed(() => Boolean(websetting.value?.vat_enabled ?? false));
const billingVatPercent = computed(() => {
  const raw = Number(websetting.value?.vat_percent ?? 0);
  return Number.isFinite(raw) ? Math.max(0, raw) : 0;
});
const totalNetIncomeValue = computed(() => {
  const raw = Number(
    page.props?.dashboardNetIncome
    ?? page.props?.cashCounterInfo?.expected_amount
    ?? page.props?.cashCounterInfo?.totals?.total_collection
    ?? page.props?.total_net_income
    ?? 0
  );
  return Number.isFinite(raw) ? raw : 0;
});
const formattedTotalNetIncome = computed(() => {
  try {
    return new Intl.NumberFormat('en-BD', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(totalNetIncomeValue.value);
  } catch (e) {
    return Number(totalNetIncomeValue.value || 0).toFixed(2);
  }
});

const openNetIncomeReport = () => {
  try {
    const url = route('backend.report.index');
    if (url) {
      window.open(url, '_blank');
      return;
    }
  } catch (e) {
    // ignore and use fallback
  }
  window.open('/backend/report', '_blank');
};

const goBack = () => {
  if (window.history.length > 1) {
    window.history.back();
    return;
  }
  router.visit(route('backend.dashboard'));
};

const reloadNetIncomeValue = () => {
  try {
    router.reload({
      only: ['dashboardNetIncome'],
      preserveState: true,
      preserveScroll: true,
    });
  } catch (e) {
    // silent fallback
  }
};

const handleDashboardStorageRefresh = (event) => {
  if (event?.key === 'dashboard:refresh') reloadNetIncomeValue();
};

// Close invoice/edit tabs when preview signals completion via localStorage
const handleCloseInvoiceTabsStorage = (event) => {
  try {
    if (event?.key === 'billing:close_invoice_tabs') {
      // Attempt to close this window if it was opened by script
      try { window.close(); } catch (e) { /* ignore */ }
    }
  } catch (e) { /* ignore */ }
};

const handleDashboardSameTabRefresh = () => {
  reloadNetIncomeValue();
};

const handleBillingWindowFocus = () => {
  reloadNetIncomeValue();
};

const isPatientModalOpen = ref(false);
const patientsList = ref([...props.patients]);

// Refs for form fields to enable Enter key navigation
const patientSearchRef = ref(null);
const doctorSearchRef = ref(null);
const patientMobileRef = ref(null);
const genderSelectRef = ref(null);
const showGenderDropdown = ref(false);
const genderSelectedIndex = ref(-1);
const genderOptions = [
  { value: 'Male', label: 'Male' },
  { value: 'Female', label: 'Female' },
  { value: 'Others', label: 'Others' }
];
const payModeRef = ref(null);
const genderWrapperRef = ref(null);
const patientMobileWrapperRef = ref(null);
const cardNumberRef = ref(null);
const discountRef = ref(null);
const extraDiscountRef = ref(null);
const receivingAmtRef = ref(null);
const deliveryDateRef = ref(null);
const deliveryTimeRef = ref(null);
const billingDateRef = ref(null);
const billingTimeRef = ref(null);
const remarksRef = ref(null);
const referrerSelectRef = ref(null);
const referrerSearch = ref("");
const showReferrerDropdown = ref(false);
const filteredReferrers = ref([]);
const highlightedRefIndex = ref(0);
const referrerJustSelected = ref(false);
const commissionSliderRef = ref(null);
const savePrintButtonRef = ref(null);
const submitOnNextReferrerEnter = ref(false);
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
const patientMobileSuggestions = ref([]);
const showPatientMobileDropdown = ref(false);
const patientMobileSelectedIndex = ref(-1);
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

// Remember last non-empty category to guard against accidental clears
const lastSelectedCategory = ref('');
let _restoringCategory = false;

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
  discountType: "flat",
  extraFlatDiscount: 0,
  vatPercentage: 0,
  vatAmount: 0,
  payableAmount: 0,
  paidAmt: 0,
  changeAmt: 0.0,
  dueAmount: 0.0,
  receivingAmt: 0.0,
  returnAmt: 0.0,
  deliveryDate: "",
  deliveryTime: "",
  remarks: "",
});

const billingDate = ref("");
const billingTime = ref("");
const billingDateTouched = ref(false);
const billingEditing = ref(false);
const billingLiveText = ref("");

// Reactive clock ticker to force re-evaluation every second
const clockNow = ref(Date.now());
let billingClockTimer = null;

// Computed display that updates every second (depends on `clockNow`)
const billingLiveDisplay = computed(() => {
  try {
    if (billingDate.value && billingTime.value) {
      const selectedDateTime = new Date(`${billingDate.value}T${billingTime.value}`);
      if (isValid(selectedDateTime)) {
        return format(selectedDateTime, 'dd-MMM-yyyy hh:mm:ss a');
      }
    }

    if (billingLiveText.value) {
      return billingLiveText.value;
    }

    return format(new Date(clockNow.value), 'dd-MMM-yyyy hh:mm:ss a');
  } catch (e) {
    return '';
  }
});
const prescriptionSearchId = ref('');
const prescriptionSearchLoading = ref(false);
const prescriptionSuggestions = ref([]);
const prescriptionSuggestionLoading = ref(false);
const showPrescriptionSuggestions = ref(false);
const prescriptionSearchInputRef = ref(null);
let billingLiveTimer = null;
let invoiceMonitorTimer = null;

const deliveryDateTouched = ref(false);
let deliveryLiveTimer = null;

const commission = ref({
  total: 0.0,
  physystAmt: 0.0,
  slider: 0,
  referrer_id: "",
  commissionRate: 0,
});

const items = ref([]);

// Track attempts to reload server props per category to avoid reload loops
const attemptedPropsReload = ref({});

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
const quickSearchDebounceMs = 100;

const reloadReferrers = () => {
  const isEditingExistingBill = Boolean(props.id && props.editData);

  if (isEditingExistingBill) {
    try {
      const ql = String(referrerSearch.value || '').trim().toLowerCase();
      const availableReferrers = Array.isArray(props.referrers) ? props.referrers : [];
      if (ql.length >= 2) {
        let filtered = availableReferrers.filter(r =>
          (r.name || '').toLowerCase().includes(ql) || (r.phone || '').toLowerCase().includes(ql)
        );
        filtered = smartSortSearchResults(filtered, referrerSearch.value, (referrer) => referrer.name);
        filteredReferrers.value = filtered;
      } else {
        filteredReferrers.value = [];
        showReferrerDropdown.value = false;
      }
    } catch (e) {
      filteredReferrers.value = [];
      showReferrerDropdown.value = false;
    }
    return;
  }

  try {
    router.reload({
      only: ["referrers"],
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page) => {
        const ql = String(referrerSearch.value || '').trim().toLowerCase();
        if (ql.length >= 2) {
          let filtered = (Array.isArray(page?.props?.referrers) ? page.props.referrers : []).filter(r =>
            (r.name || '').toLowerCase().includes(ql) || (r.phone || '').toLowerCase().includes(ql)
          );
          // Apply smart sorting
          filtered = smartSortSearchResults(filtered, referrerSearch.value, (referrer) => referrer.name);
          filteredReferrers.value = filtered;
        } else {
          filteredReferrers.value = [];
          showReferrerDropdown.value = false;
        }
      },
    });
  } catch (e) {
    filteredReferrers.value = [];
    showReferrerDropdown.value = false;
  }
};

const openCounterStartModal = () => {
  cashCounterStartBusy.value = false;
  counterStartForm.value.openingAmount = '';
  counterStartForm.value.note = 'Carry forward from previous shift';
  showCounterStartModal.value = true;
};

const closeCounterStartModal = () => {
  if (cashCounterStartBusy.value) return;
  showCounterStartModal.value = false;
};

const startCounterFromBilling = () => {
  if (cashCounterStartBusy.value) return;

  const openingAmount = Number(counterStartForm.value.openingAmount || 0);
  if (!Number.isFinite(openingAmount) || openingAmount < 0) {
    displayWarning('সঠিক opening amount দিন।');
    return;
  }

  cashCounterStartBusy.value = true;

  router.post(route('backend.cash-counter.quick-start'), {
    opening_amount: openingAmount,
    opening_note: counterStartForm.value.note || 'Started from Billing page with carry amount',
  }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      showCounterStartModal.value = false;
    },
    onError: (errors) => {
      const firstError = (() => {
        try {
          const values = Object.values(errors || {});
          return values.length ? String(values[0]) : 'Counter start ব্যর্থ হয়েছে। আবার চেষ্টা করুন।';
        } catch (e) {
          return 'Counter start ব্যর্থ হয়েছে। আবার চেষ্টা করুন।';
        }
      })();
      displayWarning(firstError);
    },
    onFinish: () => {
      cashCounterStartBusy.value = false;
    },
  });
};

const openCounterCloseModal = () => {
  cashCounterCloseBusy.value = false;
  counterCloseForm.value.closingAmount = '';
  counterCloseForm.value.note = 'Closed from Billing page quick action';
  showCounterCloseModal.value = true;
};

const closeCounterCloseModal = () => {
  if (cashCounterCloseBusy.value) return;
  showCounterCloseModal.value = false;
};

const closeCounterFromBilling = () => {
  if (cashCounterCloseBusy.value) return;

  const closingAmount = Number(counterCloseForm.value.closingAmount || 0);
  if (!Number.isFinite(closingAmount) || closingAmount < 0) {
    displayWarning('সঠিক closing amount দিন।');
    return;
  }

  cashCounterCloseBusy.value = true;
  const printWindow = window.open('', '_blank');
  if (printWindow && !printWindow.closed) {
    try {
      printWindow.document.open();
      printWindow.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Preparing Report...</title></head><body></body></html>');
      printWindow.document.close();
      try { printWindow.focus(); } catch (e) {}
    } catch (e) {
      // ignore popup document write issues
    }
  }

  try {
    const closeUrl = route('backend.cash-counter.quick-close');

    router.post(closeUrl, {
      session_id: activeCashCounterSessionId.value,
      closing_amount: closingAmount,
      note: counterCloseForm.value.note || 'Closed from Billing page quick action',
    }, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: (responsePage) => {
        const flash = responsePage?.props?.flash || {};
        const backendErrorMessage = String(flash?.errorMessage || '').trim();
        if (backendErrorMessage) {
          showCounterCloseModal.value = true;
          displayWarning(backendErrorMessage);
          if (printWindow && !printWindow.closed) {
            try {
              printWindow.document.open();
              printWindow.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Close Failed</title></head><body style="font-family:Segoe UI,Arial,sans-serif;padding:16px">${backendErrorMessage}</body></html>`);
              printWindow.document.close();
            } catch (e) {
              // ignore
            }
          }
          return;
        }

        showCounterCloseModal.value = false;

        const printUrl = responsePage?.props?.flash?.cashCounterPrintUrl || cashCounterPrintUrl.value || '';
        if (!printUrl) {
          if (printWindow && !printWindow.closed) {
            try {
              printWindow.document.open();
              printWindow.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Report Unavailable</title></head><body style="font-family:Segoe UI,Arial,sans-serif;padding:16px">Report URL পাওয়া যায়নি। আবার চেষ্টা করুন।</body></html>');
              printWindow.document.close();
            } catch (e) {
              // ignore
            }
          }
          displayWarning('রিপোর্ট URL পাওয়া যায়নি।');
          return;
        }

        if (printWindow && !printWindow.closed) {
          printWindow.location.href = printUrl;
          try { printWindow.focus(); } catch (e) {}
        } else {
          window.open(printUrl, '_blank');
        }
      },
      onError: (errors) => {
        const firstError = (() => {
          try {
            const values = Object.values(errors || {});
            return values.length ? String(values[0]) : 'Counter close ব্যর্থ হয়েছে। আবার চেষ্টা করুন।';
          } catch (e) {
            return 'Counter close ব্যর্থ হয়েছে। আবার চেষ্টা করুন।';
          }
        })();

        displayWarning(firstError);

        if (printWindow && !printWindow.closed) {
          try {
            printWindow.document.open();
            printWindow.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Close Failed</title></head><body style="font-family:Segoe UI,Arial,sans-serif;padding:16px">${firstError}</body></html>`);
            printWindow.document.close();
          } catch (e) {
            // ignore
          }
        }
      },
      onFinish: () => {
        cashCounterCloseBusy.value = false;
      },
    });
  } catch (e) {
    cashCounterCloseBusy.value = false;
    displayWarning('Counter close request তৈরি করা যায়নি। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।');
    if (printWindow && !printWindow.closed) {
      try {
        printWindow.document.open();
        printWindow.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Close Failed</title></head><body style="font-family:Segoe UI,Arial,sans-serif;padding:16px">Counter close request তৈরি করা যায়নি। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।</body></html>');
        printWindow.document.close();
      } catch (err) {
        // ignore
      }
    }
  }
};

const getCurrentDateTime = () => {
  const now = new Date();
  const date = format(now, 'yyyy-MM-dd');
  const time = format(now, 'HH:mm:ss');
  return { date, time };
};

const setCurrentBillingDateTime = () => {
  try {
    const { date, time } = getCurrentDateTime();
    billingDate.value = date;
    billingTime.value = time;
    // update human-friendly live text
    try {
      const dt = new Date(`${billingDate.value}T${billingTime.value}`);
      if (isValid(dt)) {
        billingLiveText.value = format(dt, 'dd-MMM-yyyy hh:mm:ss a');
      } else {
        billingLiveText.value = '';
      }
    } catch (e) {
      billingLiveText.value = '';
    }
  } catch (e) {
    // console.warn removed
  }

};


const startBillingLiveClock = () => {
  if (billingLiveTimer) return;
  billingLiveTimer = setInterval(() => {
    if (!billingDateTouched.value) setCurrentBillingDateTime();
  }, 1000);
};

const startBillingClockTicker = () => {
  if (billingClockTimer) return;
  billingClockTimer = setInterval(() => {
    clockNow.value = Date.now();
  }, 1000);
};

const parseBillingSourceDateTime = (source) => {
  if (!source) return null;
  const value = String(source).trim();
  let parsed = null;

  try {
    if (/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/.test(value)) {
      parsed = parse(value, 'yyyy-MM-dd HH:mm:ss', new Date());
    } else if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(value)) {
      parsed = parse(value, "yyyy-MM-dd'T'HH:mm:ss", new Date());
    } else {
      parsed = new Date(value);
    }
  } catch (e) {
    parsed = new Date(value);
  }

  return isValid(parsed) ? parsed : null;
};

const handleBillingDateTimeInput = () => {
  billingDateTouched.value = true;
  try {
    const dt = new Date(`${billingDate.value}T${billingTime.value}`);
    if (isValid(dt)) {
      billingLiveText.value = format(dt, 'dd-MMM-yyyy hh:mm:ss a');
      // normalize stored values
      billingDate.value = format(dt, 'yyyy-MM-dd');
      billingTime.value = format(dt, 'HH:mm:ss');
    }
  } catch (e) { /* ignore */ }
};
  // Calculate age from DOB helper
  const calculateAgeFromDOB = (dob) => {
  if (!dob) {
    ageYears.value = '';
    ageMonths.value = '';
    ageDays.value = '';
    return;
  }

  const birthDate = parse(String(dob), 'yyyy-MM-dd', new Date());
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
    patientSelectedIndex.value = -1;
    return;
  }

  if (isEditMode.value && !showPatientDropdown.value) {
    isEditMode.value = false;
    return;
  }

  const query = newQuery.toLowerCase();
  let filtered = props.patients.filter(patient =>
    patient.name.toLowerCase().includes(query) ||
    patient.phone.toLowerCase().includes(query)
  );

  // Apply smart sorting
  filtered = smartSortSearchResults(filtered, newQuery, (patient) => patient.name);

  filteredPatients.value = filtered;
  showPatientDropdown.value = filteredPatients.value.length > 0;
  patientSelectedIndex.value = filteredPatients.value.length > 0 ? 0 : -1;
}, quickSearchDebounceMs));

const handlePatientSearchFocus = () => {
  if (patientSearchQuery.value.trim() !== "" && filteredPatients.value.length > 0) {
    showPatientDropdown.value = true;
  }
};

const handlePatientSearchEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    if (patientSearchQuery.value.trim()) {
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
      return;
    }

    showDoctorDropdown.value = false;

    nextTick(() => {
      if (patientForm.value.gender) {
        if (dobInput.value && typeof dobInput.value.focus === 'function') {
          dobInput.value.focus();
        }
      } else {
        genderSelectRef.value?.focus();
        setTimeout(() => openDropdown(genderSelectRef), 100);
      }
    });
  }
};

const openDropdown = (ref) => {
  try {
    const el = ref?.value ?? ref;
    if (!el) return;

    if (typeof el.focus === 'function') {
      el.focus();
    }

    if (typeof el.showPicker === 'function') {
      el.showPicker();
    } else if (typeof el.click === 'function') {
      el.click();
    }
  } catch (error) {
    // ignore browser-specific picker issues
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
            // console.log removed
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

    const hasPatientMatch = Boolean(patientForm.value.patient_id) || patientMobileSuggestions.value.length > 0;

    if (hasPatientMatch) {
      focusNextPatientFieldFromMobile();
    } else {
      nextTick(() => {
        patientSearchRef.value?.focus();
      });
    }
  }
};

const focusNextPatientFieldFromGender = () => {
  if (!patientForm.value.dob) {
    dobInput.value?.focus();
  } else if (!patientForm.value.cardType) {
    // Continue with next field
  }
};

const selectGenderOption = (option) => {
  if (!option) return;

  patientForm.value.gender = option.value;
  genderSelectedIndex.value = genderOptions.findIndex((item) => item.value === option.value);
  showGenderDropdown.value = false;

  nextTick(() => {
    dobInput.value?.focus();
  });
};

const handleGenderEnter = (event) => {
  if (showGenderDropdown.value) {
    if (event.key === 'Enter') {
      event.preventDefault();
      const selectedOption = genderOptions[genderSelectedIndex.value] || genderOptions[0];
      selectGenderOption(selectedOption);
    } else if (event.key === 'ArrowDown') {
      event.preventDefault();
      genderSelectedIndex.value = Math.min(genderSelectedIndex.value + 1, genderOptions.length - 1);
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      genderSelectedIndex.value = Math.max(genderSelectedIndex.value - 1, 0);
    } else if (event.key === 'Escape') {
      event.preventDefault();
      showGenderDropdown.value = false;
    }
    return;
  }

  if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter') {
    event.preventDefault();
    showGenderDropdown.value = true;
    genderSelectedIndex.value = genderOptions.findIndex((option) => option.value === patientForm.value.gender);
    if (genderSelectedIndex.value < 0) {
      genderSelectedIndex.value = 0;
    }
  }
};

const toggleGenderDropdown = () => {
  try {
    if (!showGenderDropdown.value) {
      showGenderDropdown.value = true;
      genderSelectedIndex.value = genderOptions.findIndex((option) => option.value === patientForm.value.gender);
      if (genderSelectedIndex.value < 0) genderSelectedIndex.value = 0;
    }
  } catch (e) {
    // ignore
  }
};

const handleGenderEnterOriginal = (event) => {
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
  showGenderDropdown.value = false;
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

const selectAllOnFocus = (event) => {
  const el = event.target;
  nextTick(() => {
    el.select();
  });
};

const handleDiscountEnter = (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    nextTick(() => {
      extraDiscountRef.value?.focus();
    });
  }
};

const onDiscountWheel = (event) => {
  // Switch discount type to percentage when user scrolls on the discount input
  summary.value.discountType = 'percentage';
  // Clamp discount to allowed max percentage
  try {
    if (Number(summary.value.discount) > Number(maxBillingDiscountPercent.value)) {
      summary.value.discount = Number(maxBillingDiscountPercent.value);
    }
  } catch (e) { /* ignore */ }
  if (event && typeof event.preventDefault === 'function') event.preventDefault();
};

const handleExtraDiscountEnter = (event) => {
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
    ensureDeliveryDateTime();
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
    ensureDeliveryDateTime();

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

// Close dropdowns when clicking outside the specific wrappers
const _onDocumentClick = (e) => {
  try {
    if (showGenderDropdown.value) {
      const wrapper = genderWrapperRef.value;
      if (wrapper && !wrapper.contains(e.target)) {
        showGenderDropdown.value = false;
      }
    }
    if (showPatientMobileDropdown.value) {
      const wrapper = patientMobileWrapperRef.value;
      if (wrapper && !wrapper.contains(e.target)) {
        showPatientMobileDropdown.value = false;
      }
    }
  } catch (err) {
    // ignore errors from DOM checks in non-browser environments
  }
};

// Close dropdowns when focus moves outside (handles Tab/keyboard focus)
const _onDocumentFocusIn = (e) => {
  try {
    if (showGenderDropdown.value) {
      const wrapper = genderWrapperRef.value;
      if (wrapper && !wrapper.contains(e.target)) {
        showGenderDropdown.value = false;
      }
    }
    if (showPatientMobileDropdown.value) {
      const wrapper = patientMobileWrapperRef.value;
      if (wrapper && !wrapper.contains(e.target)) {
        showPatientMobileDropdown.value = false;
      }
    }
  } catch (err) {
    // ignore
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
          // console.log removed
        }
      }, 50);
    } catch (error) {
      // console.log removed
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

const selectPatient = (patient, options = {}) => {
  // debug log removed

  patientForm.value.patient_id = patient.id;
  patientForm.value.patientMobile = patient.phone;
  patientForm.value.gender = patient.gender;
  patientForm.value.dob = patient.dob || '';
  patientSearchQuery.value = patient.name;

  showPatientDropdown.value = false;
  patientSelectedIndex.value = -1;
  showPatientMobileDropdown.value = false;
  patientMobileSelectedIndex.value = -1;
  showGenderDropdown.value = false;
  genderSelectedIndex.value = genderOptions.findIndex(opt => opt.value === patient.gender);
  isNewPatient.value = false;

  calculateAgeFromDOB(patient.dob);

  newPatientForm.value = {
    name: "",
    phone: "",
    gender: "",
    dob: "",
  };

  if (options.focusNextField !== false) {
    nextTick(() => {
      setTimeout(() => {
        if (doctorSearchRef.value && typeof doctorSearchRef.value.focus === 'function') {
          doctorSearchRef.value.focus();
          doctorSearchRef.value.select();
        }
      }, 100);
    });
  }
};

const updatePatientMobileSuggestions = (value) => {
  const query = String(value || '').trim().toLowerCase();
  const digitsOnly = String(value || '').replace(/\D/g, '');
  // require at least 3 digits for mobile search to activate
  if (digitsOnly.length < 3) {
    patientMobileSuggestions.value = [];
    showPatientMobileDropdown.value = false;
    patientMobileSelectedIndex.value = -1;
    return;
  }
  if (!query) {
    patientMobileSuggestions.value = [];
    showPatientMobileDropdown.value = false;
    patientMobileSelectedIndex.value = -1;
    return;
  }

  const matches = (props.patients || [])
    .filter((patient) => {
      const name = String(patient.name || '').trim().toLowerCase();
      const phone = String(patient.phone || patient.mobile || '').trim().toLowerCase();
      return name.includes(query) || phone.includes(query);
    })
    .map((patient) => ({
      ...patient,
      displayPhone: String(patient.phone || patient.mobile || '').trim(),
      displayName: String(patient.name || '').trim(),
    }));

  patientMobileSuggestions.value = matches;
  showPatientMobileDropdown.value = matches.length > 0;
  patientMobileSelectedIndex.value = matches.length > 0 ? 0 : -1;
};

const handlePatientMobileInput = (event) => {
  const value = String(event?.target?.value ?? '');
  patientForm.value.patientMobile = value;

  // If the field was cleared
  if (!value.trim()) {
    // Treat as 'new entry' if isNewPatient is true OR there's no selected patient_id
    if (isNewPatient.value || !patientForm.value.patient_id) {
      // For new patient entry, only clear the mobile field
      patientForm.value.patientMobile = '';
    } else {
      // For an existing (selected) patient, clear all patient fields
      patientForm.value.patient_id = '';
      patientForm.value.patientMobile = '';
      patientForm.value.gender = '';
      patientForm.value.dob = '';
      patientSearchQuery.value = '';
      genderSelectedIndex.value = 0;
      showGenderDropdown.value = false;
      isNewPatient.value = false;
    }
    // hide suggestions
    patientMobileSuggestions.value = [];
    showPatientMobileDropdown.value = false;
    patientMobileSelectedIndex.value = -1;
    return;
  }

  updatePatientMobileSuggestions(value);
};

const focusNextPatientFieldFromMobile = () => {
  if (!patientSearchQuery.value.trim()) {
    nextTick(() => {
      patientSearchRef.value?.focus();
    });
    return;
  }

  if (!doctorSearchQuery.value.trim() && !patientForm.value.doctor_id) {
    nextTick(() => {
      doctorSearchRef.value?.focus();
      doctorSearchRef.value?.select();
    });
    return;
  }

  if (!patientForm.value.gender) {
    nextTick(() => {
      genderSelectRef.value?.focus();
      setTimeout(() => openDropdown(genderSelectRef), 100);
    });
    return;
  }

  if (!patientForm.value.dob) {
    nextTick(() => {
      dobInput.value?.focus();
    });
    return;
  }

  nextTick(() => {
    ageYearsInput.value?.focus();
  });
};

const selectPatientFromMobileSuggestion = (patient) => {
  selectPatient(patient);
  patientForm.value.patientMobile = String(patient.phone || patient.mobile || '').trim();
  patientMobileSuggestions.value = [];
  showPatientMobileDropdown.value = false;
  patientMobileSelectedIndex.value = -1;
};

const handlePatientMobileKeydown = (event) => {
  if (event.key === 'ArrowDown') {
    event.preventDefault();
    if (patientMobileSuggestions.value.length > 0) {
      patientMobileSelectedIndex.value = Math.min(patientMobileSelectedIndex.value + 1, patientMobileSuggestions.value.length - 1);
    }
  } else if (event.key === 'ArrowUp') {
    event.preventDefault();
    if (patientMobileSuggestions.value.length > 0) {
      patientMobileSelectedIndex.value = Math.max(patientMobileSelectedIndex.value - 1, 0);
    }
  } else if (event.key === 'Enter') {
    if (patientMobileSuggestions.value.length > 0 && patientMobileSelectedIndex.value !== -1) {
      event.preventDefault();
      const selectedPatient = patientMobileSuggestions.value[patientMobileSelectedIndex.value];
      if (selectedPatient) {
        selectPatientFromMobileSuggestion(selectedPatient);
      }
    } else {
      handlePatientMobileEnter(event);
    }
  } else if (event.key === 'Escape') {
    patientMobileSuggestions.value = [];
    showPatientMobileDropdown.value = false;
    patientMobileSelectedIndex.value = -1;
  }
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
  // debug log removed

  isNewPatient.value = true;
  patientForm.value.patient_id = null;

  const preservedPhone = patientForm.value.patientMobile || "";
  const preservedGender = patientForm.value.gender || "";
  const preservedDob = patientForm.value.dob || "";

  newPatientForm.value = {
    name: patientSearchQuery.value.trim(),
    phone: preservedPhone,
    gender: preservedGender,
    dob: preservedDob,
  };

  patientForm.value.patientMobile = preservedPhone;
  patientForm.value.gender = preservedGender;
  patientForm.value.dob = preservedDob;
  showGenderDropdown.value = false;
  genderSelectedIndex.value = -1;
  ageYears.value = '';
  ageMonths.value = '';
  ageDays.value = '';

  showPatientDropdown.value = false;

  // debug log removed

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
  // debug log removed

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
  // debug log removed
  if (isNewPatient.value) {
    patientForm.value.patientMobile = newPhone;
  }
});

watch(() => newPatientForm.value.gender, (newGender) => {
  // debug log removed
  if (isNewPatient.value) {
    patientForm.value.gender = newGender;
  }
});

watch(() => newPatientForm.value.dob, (newDob) => {
  // debug log removed
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
  // debug log removed
  if (isNewPatient.value && newName) {
    patientSearchQuery.value = newName;
  }
});

watch(() => isNewPatient.value, (newValue) => {
  // debug log removed
  isNewPatientFlag.value = newValue;

  if (!newValue) {
    // debug log removed
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
      showGenderDropdown.value = false;
      genderSelectedIndex.value = -1;
      patientSearchQuery.value = "";
      ageYears.value = '';
      ageMonths.value = '';
      ageDays.value = '';
    }
  } else {
    // debug log removed
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
      // keep the search input in sync with selected referrer name
      try {
        referrerSearch.value = selectedReferrer.name || '';
      } catch (e) {}
    }
  } else {
    commission.value.commissionRate = 0;
    commission.value.slider = 0;
    resetCommissionDetails();
    updateCommission();
  }
});

watch(() => props.referrers, (newReferrers) => {
  const ql = String(referrerSearch.value || '').trim().toLowerCase();
  if (ql) {
    let filtered = (newReferrers || []).filter(r => (r.name || '').toLowerCase().includes(ql) || (r.phone || '').toLowerCase().includes(ql));
    // Apply smart sorting
    filtered = smartSortSearchResults(filtered, referrerSearch.value, (referrer) => referrer.name);
    filteredReferrers.value = filtered;
    showReferrerDropdown.value = filteredReferrers.value.length > 0;
  } else {
    filteredReferrers.value = [];
    showReferrerDropdown.value = false;
  }

  if (!commission.value.referrer_id) {
    referrerSearch.value = '';
    return;
  }

  const selectedReferrer = (newReferrers || []).find(
    (referrer) => referrer.id == commission.value.referrer_id
  );

  if (selectedReferrer) {
    referrerSearch.value = selectedReferrer.name || '';
  } else {
    commission.value.referrer_id = '';
    referrerSearch.value = '';
  }
}, { deep: true });

// filter referrers as user types
watch(referrerSearch, debounce((q) => {
  // If we just selected programmatically, don't reopen suggestions
  if (referrerJustSelected.value) {
    return;
  }

  const typed = String(q || '').trim();
  const selectedReferrer = (props.referrers || []).find(
    (referrer) => referrer.id == commission.value.referrer_id
  );

  if (selectedReferrer) {
    const selectedName = String(selectedReferrer.name || '').trim().toLowerCase();
    const selectedPhone = String(selectedReferrer.phone || '').trim().toLowerCase();
    const typedLower = typed.toLowerCase();

    if (!typedLower || (typedLower !== selectedName && typedLower !== selectedPhone)) {
      commission.value.referrer_id = '';
      commission.value.commissionRate = 0;
      commission.value.slider = 0;
      resetCommissionDetails();
      updateCommission();
    }
  }

  const ql = String(q || '').trim().toLowerCase();
  if (!ql) {
    filteredReferrers.value = [];
    showReferrerDropdown.value = false;
    highlightedRefIndex.value = 0;
    return;
  }
  let filtered = (props.referrers || []).filter(r => (r.name || '').toLowerCase().includes(ql) || (r.phone || '').toLowerCase().includes(ql));

  // Apply smart sorting
  filtered = smartSortSearchResults(filtered, q, (referrer) => referrer.name);

  filteredReferrers.value = filtered;
  highlightedRefIndex.value = 0;
  showReferrerDropdown.value = filteredReferrers.value.length > 0;
}, quickSearchDebounceMs));

const selectReferrer = (r) => {
  // set flag first so programmatic setting of referrerSearch doesn't reopen dropdown
  referrerJustSelected.value = true;
  commission.value.referrer_id = r.id;
  referrerSearch.value = r.name || '';
  showReferrerDropdown.value = false;
  // clear suggestions to ensure dropdown doesn't reappear
  filteredReferrers.value = [];
  // blur input to avoid double Enter behavior and hide dropdown reliably
  try {
    if (referrerSelectRef && referrerSelectRef.value && typeof referrerSelectRef.value.blur === 'function') {
      referrerSelectRef.value.blur();
    }
  } catch (e) {}
  updateCommissionRate(r);
  submitOnNextReferrerEnter.value = true;
  // Move focus to Save & Print so the next Enter submits quickly.
  nextTick(() => {
    setTimeout(() => {
      if (savePrintButtonRef.value && typeof savePrintButtonRef.value.focus === 'function') {
        savePrintButtonRef.value.focus();
      }
    }, 60);
  });
  // keep the flag a bit longer to avoid immediate reopen on focus
  setTimeout(() => { referrerJustSelected.value = false; }, 400);
};

const handleReferrerKeydown = (e) => {
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    if (!showReferrerDropdown.value && filteredReferrers.value.length>0) showReferrerDropdown.value = true;
    highlightedRefIndex.value = Math.min(highlightedRefIndex.value + 1, filteredReferrers.value.length - 1);
    return;
  }
  if (e.key === 'ArrowUp') {
    e.preventDefault();
    highlightedRefIndex.value = Math.max(highlightedRefIndex.value - 1, 0);
    return;
  }
  if (e.key === 'Enter') {
    if (showReferrerDropdown.value && filteredReferrers.value.length > 0) {
      e.preventDefault();
      e.stopPropagation();
      const pick = filteredReferrers.value[highlightedRefIndex.value] || filteredReferrers.value[0];
      if (pick) selectReferrer(pick);
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    if (submitOnNextReferrerEnter.value) {
      submitOnNextReferrerEnter.value = false;
      saveBill();
      return;
    }

    showReferrerDropdown.value = false;
    submitOnNextReferrerEnter.value = true;
    nextTick(() => {
      setTimeout(() => {
        if (savePrintButtonRef.value) {
          if (typeof savePrintButtonRef.value.scrollIntoView === 'function') {
            savePrintButtonRef.value.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
          if (typeof savePrintButtonRef.value.focus === 'function') {
            savePrintButtonRef.value.focus();
          }
        }
      }, 60);
    });
    return;
  }
  if (e.key === 'Escape') {
    showReferrerDropdown.value = false;
  }
};

const handleReferrerFocus = (e) => {
  // avoid re-opening immediately after a selection via Enter
  if (referrerJustSelected.value) {
    showReferrerDropdown.value = false;
    return;
  }
  // Close other open dropdowns (e.g., Gender) when focusing referrer
  showGenderDropdown.value = false;
  const ql = String(referrerSearch.value || '').trim().toLowerCase();
  if (ql.length < 2) {
    showReferrerDropdown.value = false;
    return;
  }
  showReferrerDropdown.value = filteredReferrers.value.length > 0;
};

const handleReferrerBlur = () => {
  setTimeout(() => {
    showReferrerDropdown.value = false;
  }, 120);
};

watch(() => commission.value.slider, (newValue) => {
  if (commissionDetails.value.manualCommissionEnabled) {
    commission.value.commissionRate = parseFloat(newValue);
  }
  updateCommission();
});

watch(() => summary.value.extraFlatDiscount, () => {
  updateSummary();
});

watch(() => summary.value.receivingAmt, () => {
  calculateChangeAndDue();
});

watch(() => summary.value.payableAmount, () => {
  calculateChangeAndDue();
});

watch([() => summary.value.discount, () => summary.value.discountType], () => {
  updateSummary();
});

watch(() => commission.value.slider, () => {
  commission.value.commissionRate = commission.value.slider;
  updateCommission();
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

// Debug: log when category changes to trace unexpected updates
watch(() => itemForm.value.category, (newCat, oldCat) => {
  // debug log removed
  try {
    const sc = normalizeForMatch(String(newCat || ''));
    const availableCats = Array.from(new Set((allAvailableItems.value||[]).map(i => String(i.category||'').trim()).filter(Boolean)));
    const matches = (allAvailableItems.value||[]).filter(it => {
      const icat = normalizeForMatch(it.category || '');
      const iname = normalizeForMatch(it.name || '');
      const ialt = normalizeForMatch(it.alt || '');
      if (!sc) return false;
      if (icat && (icat === sc || icat.includes(sc) || sc.includes(icat))) return true;
      if (iname && iname.includes(sc)) return true;
      if (ialt && ialt.includes(sc)) return true;
      const scTokens = sc.split(' ').filter(Boolean);
      if (scTokens.length > 0) {
        if (scTokens.some(tok => icat.includes(tok) || iname.includes(tok) || ialt.includes(tok))) return true;
      }
      return false;
    });
    // debug log removed
    // Track last non-empty selection
    if (newCat && String(newCat).trim() !== '') {
      lastSelectedCategory.value = newCat;
      lastSelectedCategory._ts = Date.now();
    }
  } catch (e) { /* debug warn removed */ }
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
    ...new Set(items.value.map((item) => normalizeCommissionCategory(item.category))),
  ];

  const availableCommissions = {
    pathology: referrer.pathology_commission || 0,
    radiology: referrer.radiology_commission || 0,
    medicine: referrer.pharmacy_commission || 0,
    opd: referrer.opd_commission || 0,
    ipd: referrer.ipd_commission || 0,
    ecg: referrer.ecg_commission || referrer.radiology_commission || 0,
    ultrasound: referrer.ultrasound_commission || referrer.radiology_commission || 0,
    appointment: referrer.opd_commission || 0,
  };

  commissionDetails.value.hasPathologyCommission =
    itemCategories.includes("pathology") && availableCommissions.pathology > 0;
  commissionDetails.value.hasRadiologyCommission =
    itemCategories.includes("radiology") && availableCommissions.radiology > 0;
  commissionDetails.value.hasMedicineCommission =
    itemCategories.includes("medicine") && availableCommissions.medicine > 0;
  commissionDetails.value.hasECGCommission =
    itemCategories.includes("ecg") && availableCommissions.ecg > 0;
  commissionDetails.value.hasUltrasoundCommission =
    itemCategories.includes("ultrasound") && availableCommissions.ultrasound > 0;

  commissionDetails.value.pathologyRate = availableCommissions.pathology;
  commissionDetails.value.radiologyRate = availableCommissions.radiology;
  commissionDetails.value.medicineRate = availableCommissions.medicine;
  commissionDetails.value.ecgRate = availableCommissions.ecg;
  commissionDetails.value.ultrasoundRate = availableCommissions.ultrasound;

  let totalCommissionAmount = 0;
  let categoriesWithoutCommission = [];
  let categoriesWithCommission = [];

  const categoryData = {};

  items.value.forEach((item) => {
    const category = normalizeCommissionCategory(item.category);
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
    const itemsInCategory = categoryData[category].items || [];
    const categoryAmount = categoryData[category].totalAmount;
    let categoryCommissionAmount = 0;

    itemsInCategory.forEach((item) => {
      const itemAmount = Number(item.netAmount || 0);
      const itemLevelRate = Number(item.referral_percentage || 0);
      const rate = itemLevelRate > 0 ? itemLevelRate : getCategoryCommissionRate(referrer, category);
      categoryCommissionAmount += (itemAmount * rate) / 100;
    });

    if (categoryCommissionAmount > 0) {
      categoriesWithCommission.push(category);
      totalCommissionAmount += categoryCommissionAmount;
    } else {
      categoriesWithoutCommission.push(category);
    }
  });

  if (categoriesWithoutCommission.length > 0 && categoriesWithCommission.length === 0) {
    commissionDetails.value.noCommissionMessage = `This referrer doesn't have commission for: ${categoriesWithoutCommission.join(', ')}`;
    commission.value.commissionRate = 0;
    commission.value.slider = 0;
    commissionDetails.value.manualCommissionEnabled = true;
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
    hasECGCommission: false,
    hasUltrasoundCommission: false,
    pathologyRate: 0,
    radiologyRate: 0,
    medicineRate: 0,
    ecgRate: 0,
    ultrasoundRate: 0,
    manualCommissionEnabled: false,
    noCommissionMessage: "",
  };
};

const normalizeCommissionCategory = (rawCategory) => {
  const cat = String(rawCategory || "").trim().toLowerCase();
  if (!cat) return "";

  if (["pathology", "pathological"].includes(cat)) return "pathology";
  if (["medicine", "pharmacy", "drug"].includes(cat)) return "medicine";
  if (["ecg", "ekg"].includes(cat)) return "ecg";
  if (["ultrasound", "ultrasonogram", "ultrasonography", "usg", "sono"].includes(cat)) return "ultrasound";
  if (["radiology", "xray", "x-ray", "ct", "mri"].includes(cat)) return "radiology";
  if (["opd", "appointment"].includes(cat)) return cat === "appointment" ? "appointment" : "opd";
  if (["ipd"].includes(cat)) return "ipd";

  return cat;
};

const getCategoryCommissionRate = (referrer, rawCategory) => {
  const category = normalizeCommissionCategory(rawCategory);
  const radiologyFallback = Number(referrer?.radiology_commission || 0);
  switch (category) {
    case "pathology":
      return Number(referrer?.pathology_commission || 0);
    case "radiology":
      return Number(referrer?.radiology_commission || 0);
    case "ecg":
      return Number(referrer?.ecg_commission || radiologyFallback || 0);
    case "ultrasound":
      return Number(referrer?.ultrasound_commission || radiologyFallback || 0);
    case "medicine":
      return Number(referrer?.pharmacy_commission || 0);
    case "opd":
      return Number(referrer?.opd_commission || 0);
    case "ipd":
      return Number(referrer?.ipd_commission || 0);
    case "appointment":
      return Number(referrer?.opd_commission || 0);
    default:
      return 0;
  }
};

const updateCommission = () => {
  const totalAmount = summary.value.payableAmount || commission.value.total;
  const commissionPercent = commission.value.commissionRate / 100;
  // Prefer summing per-category rounded commissions when available to avoid tiny FP mismatches
  try {
    if (!commissionDetails.value.manualCommissionEnabled && commissionBreakdown.value && commissionBreakdown.value.length > 0) {
      const totalFromBreakdown = commissionBreakdown.value.reduce((s, b) => s + (parseFloat(b.commission) || 0), 0);
      commission.value.physystAmt = parseFloat(totalFromBreakdown.toFixed(2));
      return;
    }
  } catch (e) { /* fallback to percent calc */ }

  commission.value.physystAmt = parseFloat((totalAmount * commissionPercent).toFixed(2));
};

const calculateChangeAndDue = () => {
  const roundMoney = (value) => parseFloat((value || 0).toFixed(2));
  const payableAmount = Math.max(0, parseFloat(summary.value.payableAmount) || 0);
  const receivingAmount = Math.max(0, parseFloat(summary.value.receivingAmt) || 0);
  const isEditingExistingBill = Boolean(isEditMode.value || (props.id && props.editData));

  const originalGrossPaid = isEditingExistingBill
    ? Math.max(0, parseFloat(props.editData?.paid_amt) || 0)
    : 0;
  const existingReturnAmount = isEditingExistingBill
    ? Math.max(0, parseFloat(props.editData?.return_amt) || 0)
    : 0;

  // existingReturnAmount represents previously returned/refunded amount that
  // reduces the effective paid total. Subtract it from original paid to get
  // the true gross received amount prior to new receiving input.
  const grossReceived = Math.max(0, originalGrossPaid - existingReturnAmount);
  const totalGrossReceived = grossReceived + receivingAmount;
  const effectivePaid = Math.min(payableAmount, totalGrossReceived);
  const returnAmount = Math.max(0, totalGrossReceived - payableAmount);
  const dueAmount = Math.max(0, payableAmount - effectivePaid);

  summary.value.receivingAmt = roundMoney(receivingAmount);
  summary.value.paidAmt = roundMoney(effectivePaid);
  summary.value.returnAmt = roundMoney(returnAmount);
  summary.value.changeAmt = 0;
  summary.value.dueAmount = roundMoney(dueAmount);
};

const commissionBreakdown = computed(() => {
  if (!commission.value.referrer_id || items.value.length === 0) return [];

  const breakdown = [];
  const selectedReferrer = props.referrers.find(
    (referrer) => referrer.id == commission.value.referrer_id
  );

  if (!selectedReferrer) return breakdown;

  const categoryTotals = items.value.reduce((acc, item) => {
    const category = normalizeCommissionCategory(item.category);
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
    const itemsInCat = categoryTotals[category].items || [];
    let categoryAmount = 0;
    let categoryCommission = 0;
    let rateName = '';

    let weightedRateSum = 0;
    itemsInCat.forEach((it) => {
      const itemRate = (it.referral_percentage && Number(it.referral_percentage) > 0)
        ? Number(it.referral_percentage)
        : getCategoryCommissionRate(selectedReferrer, category);
      const amt = Number(it.netAmount || 0);
      categoryAmount += amt;
      categoryCommission += (amt * itemRate) / 100;
      weightedRateSum += (itemRate * amt);
    });
    const categoryRate = categoryAmount > 0 ? (weightedRateSum / categoryAmount) : 0;

    switch (category) {
      case "pathology": rateName = "Pathology Commission"; break;
      case "radiology": rateName = "Radiology Commission"; break;
      case "ecg": rateName = "ECG Commission"; break;
      case "ultrasound": rateName = "Ultrasound Commission"; break;
      case "medicine": rateName = "Pharmacy Commission"; break;
      case "opd": rateName = "OPD Commission"; break;
      case "ipd": rateName = "IPD Commission"; break;
      case "appointment": rateName = "Appointment Commission"; break;
    }

    breakdown.push({
      category: category.charAt(0).toUpperCase() + category.slice(1),
      rateName,
      rate: parseFloat(categoryRate.toFixed(2)),
      amount: categoryAmount,
      commission: parseFloat(categoryCommission.toFixed(2)),
      hasCommission: categoryCommission > 0,
    });
  });

  return breakdown;
});

// Defer heavy item normalization until after initial render to speed up tab open
const itemsReady = ref(false);
onMounted(() => {
  // small delay to allow UI to render first, then compute items
  setTimeout(() => { itemsReady.value = true; }, 30);
});

const allAvailableItems = computed(() => {
  if (!itemsReady.value) return [];
  const tests = props.pathologyAndRadiologyTests.map((test) => ({
    id: test.id,
    name: test.test_name,
    category: test.category_type,
    unitPrice: test.amount,
    referral_percentage: test.referral_percentage ?? 0,
    type: "test",
    room_no: test.room_no ?? '',
    alt: [test.test_name, test.short_name ?? '', test.charge_name ?? '', test.category_type ?? '']
      .concat(Object.values(test || {}).filter(v => (typeof v === 'string' || typeof v === 'number')).slice(0,10))
      .filter(Boolean).join(' | '),
    normalizedCategories: [test.category_type, test.module, test.charge_category, test.main_category].filter(Boolean).map(normalizeForMatch),
    is_disposable: (isDisposableValue(test.test_name) || isDisposableValue(test.category_type) || isDisposableValue(test.short_name) || isDisposableValue(test.charge_name)),
  }));

  const medicines = props.medicineInventories
    .filter((medicine) => medicine.status === "Active")
    .map((medicine) => ({
      id: medicine.id,
      name: medicine.medicine_name,
      category: "Medicine",
      unitPrice: medicine.medicine_unit_selling_price,
      stock: medicine.medicine_quantity,
      referral_percentage: medicine.referral_percentage ?? 0,
      type: "medicine",
      alt: [medicine.medicine_name, medicine.medicine_code ?? '', medicine.medicine_brand ?? '']
        .concat(Object.values(medicine || {}).filter(v => (typeof v === 'string' || typeof v === 'number')).slice(0,10))
        .filter(Boolean).join(' | '),
      normalizedCategories: [medicine.category || 'Medicine', medicine.sub_category || medicine.type].filter(Boolean).map(normalizeForMatch),
      is_disposable: (isDisposableValue(medicine.medicine_name) || Object.keys(medicine||{}).some(k=> /dispos|ডিসপ/i.test(String(k)))),
    }));

    // Map any hospitalCharges provided by the backend into charge items
    const charges = (props.hospitalCharges || []).map((charge) => {
      // backend may provide `module` as a JSON-encoded array string or plain string
      let cat = '';
      try {
        if (charge.module) {
          try {
            const parsed = JSON.parse(charge.module);
            if (Array.isArray(parsed) && parsed.length > 0) cat = String(parsed[0]);
            else cat = String(parsed || '');
          } catch (e) {
            cat = String(charge.module || '');
          }
        }
      } catch (e) { cat = String(charge.module || ''); }

      return {
        id: charge.id,
        name: charge.name,
        category: cat || '',
        unitPrice: charge.amount ?? 0,
        type: 'charge',
        alt: [charge.name, charge.short_name ?? ''].filter(Boolean).join(' | '),
        normalizedCategories: (cat ? [normalizeForMatch(cat)] : []),
        is_disposable: /dispos|ডিসপ/i.test(String(charge.name || '') + String(cat || '')),
      };
    });

    // Post-process items to coerce disposable detection into category when needed
    const allItems = [...tests, ...medicines, ...charges];
  const disposableKeywords = ['tube','v tube','v.tube','syringe','needle','glove','gauze','dispos'];
  allItems.forEach(it => {
    try {
      const rawHay = ((it.name||'') + ' ' + (it.alt||'')).toLowerCase();
      const normalizedHay = normalizeForMatch(rawHay);
      // Match disposable keywords as whole words (use word boundaries) on normalized text
      const detectedByKeyword = disposableKeywords.some(k => {
        const nk = normalizeForMatch(k);
        if (!nk) return false;
        const re = new RegExp('\\b' + nk.replace(/\\s+/g, '\\s+') + '\\b');
        return re.test(normalizedHay);
      });
      const detected = it.is_disposable || detectedByKeyword;
      if (detected) {
        // For non-medicine items, coerce category to Disposable so category filters work.
        // Do NOT coerce medicines into the Disposable category to avoid showing
        // pharmacy/medicine rows when user selects Disposable.
        try {
          it.normalizedCategories = Array.isArray(it.normalizedCategories) ? it.normalizedCategories : [];
        } catch(e) { it.normalizedCategories = []; }

        if (it.type !== 'medicine') {
          it.category = 'Disposable';
          if (!it.normalizedCategories.some(nc => nc.includes('dispos'))) {
            it.normalizedCategories.push(normalizeForMatch('Disposable'));
          }
        } else {
          // mark medicine as disposable-flagged but keep its category as Medicine
          it.is_disposable = true;
        }
      }
    } catch(e){}
  });

  return allItems;
});

// Unique category types for dropdowns (includes known defaults + detected ones)
const categoryTypes = computed(() => {
  const set = new Set();

  (props.pathologyAndRadiologyTests || []).forEach(t => {
    const v = (t.category_type || '').toString().trim();
    if (v) set.add(v);
  });

  if ((props.medicineInventories || []).length > 0) set.add('Medicine');

  // include hospitalCharges from props and any extra fetched charges
  // hospitalCharges categories removed; categories come from tests and medicines above

  // Ensure a set of common types are always present
  ['Pathology','Radiology','Medicine','OPD','IPD','Appointment','ECG','Ultrasound','Blood Bank','Ambulance','Disposable']
    .forEach(s => set.add(s));

  return Array.from(set);
});

// debug logging removed to reduce console noise

const deliveryDateFormatted = computed(() => {
  const raw = summary.value.deliveryDate;
  if (!raw) return "";
  try {
    const d = new Date(raw);
    return format(d, 'dd-MMM-yyyy');
  } catch (e) {
    return "";
  }
});

// Expose debug helpers when running in browser DevTools so users can inspect
// normalized items and category types even when Vue internals aren't available.
// removed dev debug helpers to reduce startup work and console noise

// Fetch hospital charges on mount to ensure recently-created charges
// (e.g., Disposable items) created outside this page are available.
// hospitalCharges fetch removed — Billing uses Item/Test lists only

// Additional debug helpers to troubleshoot missing items
onMounted(() => {
  try {
    window.__billing_debug.getPropCounts = () => ({
      pathologyAndRadiologyTests: (props.pathologyAndRadiologyTests || []).length,
      medicineInventories: (props.medicineInventories || []).length,
    });

    window.__billing_debug.search = (term) => {
      const raw = String(term || '').trim();
      if (!raw) return [];
      const tokens = raw.split(/\s+/).map(t => normalizeForMatch(t)).filter(Boolean);
      if (!tokens.length) return [];

      const hits = (allAvailableItems.value || []).filter(it => {
        try {
          const hay = normalizeForMatch(((it.name||'') + ' ' + (it.alt||'') + ' ' + (it.category||'') + ' ' + (JSON.stringify(it || {}))));
          // tokenized match: require every token to appear somewhere in the haystack
          const tokensMatch = tokens.every(tok => hay.includes(tok));
          const catsMatch = Array.isArray(it.normalizedCategories) && tokens.every(tok => it.normalizedCategories.some(nc => nc.includes(tok)));
          return tokensMatch || catsMatch;
        } catch (e) { return false; }
      });
      // debug log removed
      return hits.slice(0, 50);
    };
  } catch (e) {}
});

const filteredItems = computed(() => {
  const rawQuery = String(searchQuery.value || '').trim().toLowerCase();
  const tokens = rawQuery.length ? rawQuery.split(/\s+/).filter(Boolean) : [];
  const normalizedTokens = tokens.length ? tokens.map(t => normalizeForMatch(t)).filter(Boolean) : [];

  const buildTestItem = (test) => ({
    id: test.id,
    name: (test.test_name || '').trim(),
    category: test.category_type || '',
    unitPrice: test.amount ?? 0,
    referral_percentage: test.referral_percentage ?? 0,
    type: 'test',
    // include extra searchable fields
    alt: [test.test_name, test.short_name ?? '', test.charge_name ?? ''].filter(Boolean).join(' | '),
  });

  const buildMedicineItem = (medicine) => ({
    id: medicine.id,
    name: (medicine.medicine_name || '').trim(),
    category: 'Medicine',
    unitPrice: medicine.medicine_unit_selling_price ?? 0,
    referral_percentage: medicine.referral_percentage ?? 0,
    stock: medicine.medicine_quantity ?? 0,
    type: 'medicine',
    alt: [medicine.medicine_name, medicine.medicine_code ?? ''].filter(Boolean).join(' | '),
  });

  const buildChargeItem = (charge) => ({
    id: charge.id,
    name: (charge.name || '').trim(),
    category: charge.module || '',
    unitPrice: charge.amount ?? 0,
    type: 'charge',
    alt: [charge.name, charge.short_name ?? ''].filter(Boolean).join(' | '),
  });

  let itemsToFilter = [];

  if (itemForm.value.category) {
    const selectedCategory = String(itemForm.value.category || '').toLowerCase();

    // If user typed tokens, perform a global name-based match across all items
    // so typing a name will show matching items even when no category selected.
    if (tokens.length > 0) {
      const tokenMatchesAll = allAvailableItems.value.filter((item) => {
        try {
          const hay = normalizeForMatch(((item.name || '') + ' ' + (item.alt || '')));
          if (!normalizedTokens.length) return false;
          return normalizedTokens.every((tok) => hay.includes(tok));
        } catch (e) { return false; }
      });

      // Prefer token matches that also match the selected category.
      // Do NOT fall back to tokenMatchesAll when a category is selected;
      // instead perform strict category-only matching if tokens don't yield
      // category matches.
      const tokenCategoryMatches = tokenMatchesAll.filter((it) => {
        try {
          const icat = normalizeForMatch(it.category || '');
          const sc = normalizeForMatch(selectedCategory || '');
          // treat OPD and Appointment as synonyms for matching
          if ((sc === 'appointment' && icat === 'opd') || (sc === 'opd' && icat === 'appointment')) return true;
          if (icat && (icat === sc || icat.includes(sc) || sc.includes(icat))) return true;
          if (Array.isArray(it.normalizedCategories) && it.normalizedCategories.length > 0) {
            if (it.normalizedCategories.some(nc => nc === sc || nc.includes(sc) || sc.includes(nc))) return true;
          }
          return false;
        } catch (e) { return false; }
      });
      if (tokenCategoryMatches.length > 0) {
        // When a category is selected, prefer strict category-matching token
        // results only — do NOT include other-category token matches.
        itemsToFilter = tokenCategoryMatches;
      } else if (tokenMatchesAll.length > 0) {
        // No category-specific token matches, but there are token matches
        // across other categories — show them (name-based search).
        itemsToFilter = tokenMatchesAll;
      } else {
        // If tokens exist but none matched by name, fall back to strict
        // category-only matching so selecting a category filters items.
        itemsToFilter = allAvailableItems.value.filter((it) => {
          const icat = normalizeForMatch(it.category || '');
          const iname = normalizeForMatch(it.name || '');
          const ialt = normalizeForMatch(it.alt || '');
          const sc = normalizeForMatch(selectedCategory || '');
          if (!sc) return false;

          if (icat && (icat === sc || icat.includes(sc) || sc.includes(icat))) return true;
          // treat OPD and Appointment as synonyms for matching
          if ((sc === 'appointment' && icat === 'opd') || (sc === 'opd' && icat === 'appointment')) return true;
          try {
            if (Array.isArray(it.normalizedCategories) && it.normalizedCategories.length > 0) {
              if (it.normalizedCategories.some(nc => nc === sc || nc.includes(sc) || sc.includes(nc))) return true;
            }
          } catch (e) {}
          if (iname && iname.includes(sc)) return true;
          if (ialt && ialt.includes(sc)) return true;
          const scTokens = sc.split(' ').filter(Boolean);
          if (scTokens.length > 0) {
            const anyTokenMatch = scTokens.some(tok => iname.includes(tok) || ialt.includes(tok) || icat.includes(tok) || (Array.isArray(it.normalizedCategories) && it.normalizedCategories.some(nc => nc.includes(tok))));
            if (anyTokenMatch) return true;
          }
          try {
            if (sc.includes('dispos')) {
              // Exclude medicine items from Disposable matches unless they are
              // explicitly categorized as Disposable on the server side.
              if (it.type === 'medicine') {
                if (String(it.category || '').toLowerCase().includes('dispos')) return true;
              } else {
                if (it.is_disposable) return true;
                const hasDisposableFlag = Object.keys(it || {}).some(k => String(k || '').toLowerCase().includes('dispos') && Boolean(it[k]));
                if (hasDisposableFlag) return true;
              }
            }
          } catch (e) { /* ignore */ }
          return false;
        });
      }
    } else {
      // No tokens typed; perform category-only matching
      const sc = normalizeForMatch(String(selectedCategory || ''));
      itemsToFilter = allAvailableItems.value.filter((it) => {
        const icat = normalizeForMatch(it.category || '');
        const iname = normalizeForMatch(it.name || '');
        const ialt = normalizeForMatch(it.alt || '');
        if (!sc) return false;
        if (icat && (icat === sc || icat.includes(sc) || sc.includes(icat))) return true;
        try {
          if (Array.isArray(it.normalizedCategories) && it.normalizedCategories.length > 0) {
            if (it.normalizedCategories.some(nc => nc === sc || nc.includes(sc) || sc.includes(nc))) return true;
          }
        } catch (e) {}
        if (iname && iname.includes(sc)) return true;
        if (ialt && ialt.includes(sc)) return true;
        const scTokens = sc.split(' ').filter(Boolean);
        if (scTokens.length > 0) {
          const anyTokenMatch = scTokens.some(tok => iname.includes(tok) || ialt.includes(tok) || icat.includes(tok) || (Array.isArray(it.normalizedCategories) && it.normalizedCategories.some(nc => nc.includes(tok))));
          if (anyTokenMatch) return true;
        }
        try {
          if (sc.includes('dispos')) {
            if (it.is_disposable) return true;
            const hasDisposableFlag = Object.keys(it || {}).some(k => String(k || '').toLowerCase().includes('dispos') && Boolean(it[k]));
            if (hasDisposableFlag) return true;
          }
        } catch (e) { /* ignore */ }
        return false;
      });

      // Fallback: if user selected "disposable" but category-based lookup
      // found nothing, include any item that is flagged as disposable via
      // name/flags/normalizedCategories. This handles items whose explicit
      // `category` field isn't set to "Disposable" but are still disposable.
      try {
        if ((String(sc || '').includes('dispos')) && itemsToFilter.length === 0) {
          itemsToFilter = (allAvailableItems.value || []).filter(it => {
            try {
              if (!it) return false;
              // exclude medicines unless explicitly categorized as Disposable
              if (it.type === 'medicine') {
                if (String(it.category || '').toLowerCase().includes('dispos')) return true;
                return false;
              }
              // only include items explicitly flagged as disposable or categorized so
              if (it.is_disposable) return true;
              if (Array.isArray(it.normalizedCategories) && it.normalizedCategories.some(nc => nc.includes('dispos'))) return true;
              if (String(it.category || '').toLowerCase().includes('dispos')) return true;
            } catch (e) { /* ignore */ }
            return false;
          });
        }
      } catch (e) { /* ignore */ }
      // Extra fallback: search the full serialized object for 'dispos' token
      // Removed broad serialized-object fallback to avoid false-positive matches
    }

    // Debugging: log available categories and matches for troubleshooting
    try {
      const availableCats = Array.from(new Set(allAvailableItems.value.map(i => String(i.category || '').trim()).filter(Boolean)));
      if (selectedCategory === 'disposable' || itemsToFilter.length === 0) {
        // debug log removed
      }

      // If user selected a category but no matches found, try to refresh
      // server-side props once for this category so newly created items
      // (created in a different admin UI) become available in `allAvailableItems`.
      try {
              if (selectedCategory && itemsToFilter.length === 0) {
          const key = String(selectedCategory).toLowerCase();
          if (!attemptedPropsReload.value[key]) {
            attemptedPropsReload.value[key] = true;
            // debug log removed
            try {
              // Force a fresh props fetch (do not preserve state) so newly-created
              // charges/items become available to this page.
                    router.reload({ only: ['pathologyAndRadiologyTests','medicineInventories'], preserveState: false, preserveScroll: false, onSuccess: (page) => {
                try { window.__billing_debug.latest.items = allAvailableItems.value; } catch(e){}
                // debug log removed
              } });
            } catch (e) { /* console.warn removed */ }
          }
        }
      } catch (e) {}
    } catch (e) { /* console.warn removed */ }
  } else {
    // Reuse pre-built unified list to avoid rebuilding arrays on every computed evaluation
    itemsToFilter = allAvailableItems.value;
  }

  // If no query, return items as-is (possibly filtered by category)
  if (!tokens.length) return itemsToFilter;

  // Match items where every token appears in either the name or alt fields
  if (!normalizedTokens.length) return itemsToFilter;
  
  // Filter items that match the search query
  const matchedItems = itemsToFilter.filter((item) => {
    const hay = normalizeForMatch(((item.name || '') + ' ' + (item.alt || '')));
    return normalizedTokens.every((tok) => hay.includes(tok));
  });

  // Smart sorting: prioritize by match quality and position
  const normalizedQuery = normalizeForMatch(rawQuery);
  
  return matchedItems.sort((a, b) => {
    const normalizedAName = normalizeForMatch(a.name || '');
    const normalizedAAlt = normalizeForMatch(a.alt || '');
    const normalizedBName = normalizeForMatch(b.name || '');
    const normalizedBAlt = normalizeForMatch(b.alt || '');
    
    // Helper function to calculate match score
    const getMatchScore = (itemName, itemAlt) => {
      // Priority 1: Name starts with exact query
      if (itemName.startsWith(normalizedQuery)) return 100;
      
      // Priority 2: Alt starts with exact query
      if (itemAlt.startsWith(normalizedQuery)) return 95;
      
      // Priority 3: Name starts with first character
      if (normalizedQuery.length > 0 && itemName.startsWith(normalizedQuery.charAt(0))) return 90;
      
      // Priority 4: Alt starts with first character
      if (normalizedQuery.length > 0 && itemAlt.startsWith(normalizedQuery.charAt(0))) return 85;
      
      // Priority 5: Query appears at word boundary in name
      const nameWords = itemName.split(/\s+/);
      if (nameWords.some(word => word.startsWith(normalizedQuery))) return 80;
      
      // Priority 6: Query appears at word boundary in alt
      const altWords = itemAlt.split(/\s+/);
      if (altWords.some(word => word.startsWith(normalizedQuery))) return 75;
      
      // Priority 7: Exact substring match in name
      if (itemName.includes(normalizedQuery)) return 70;
      
      // Priority 8: Exact substring match in alt
      if (itemAlt.includes(normalizedQuery)) return 65;
      
      // Priority 9: Token-based match (default, already filtered)
      return 50;
    };
    
    const scoreA = getMatchScore(normalizedAName, normalizedAAlt);
    const scoreB = getMatchScore(normalizedBName, normalizedBAlt);
    
    return scoreB - scoreA; // Higher score comes first
  });
});

// Dynamic style for items container: grows with number of items, enables scroll after a max height
const itemsContainerStyle = computed(() => {
  const itemRowHeight = 38; // approx row height in px
  const headerHeight = 48; // header + padding
  const calculated = headerHeight + items.value.length * itemRowHeight;
  const maxAllowed = 600;
  const height = Math.min(calculated, maxAllowed);
  const overflow = calculated > maxAllowed ? 'auto' : 'visible';
  return {
    maxHeight: `${height}px`,
    overflowY: overflow,
    transition: 'max-height 200ms ease',
  };
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
      displayWarning({ message: "This item has already been added to the cart." });
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
    type: (allAvailableItems.value.find(i => i.id === itemForm.value.itemId) || {}).type || '',
    unitPrice: parseFloat(itemForm.value.unitPrice),
    referral_percentage: (allAvailableItems.value.find(i => i.id === itemForm.value.itemId) || {}).referral_percentage ?? 0,
    roomNo: (allAvailableItems.value.find(i => i.id === itemForm.value.itemId) || {}).room_no ?? '',
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

const normalizeText = (value) => String(value ?? '').trim().toLowerCase();

// Normalize for matching: remove non-alphanumeric, collapse spaces, lowercase
const normalizeForMatch = (v) => {
  if (v == null) return '';
  return String(v)
    .toLowerCase()
    .replace(/[^a-z0-9\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
};

const isDisposableValue = (v) => {
  if (v == null) return false;
  try {
    const s = String(v).toLowerCase();
    return /dispos|ডিসপ/.test(s);
  } catch (e) { return false; }
};

// Smart sorting helper for search results
const smartSortSearchResults = (items, queryString, getDisplayText = (item) => item.name || '') => {
  if (!queryString || !queryString.trim()) return items;

  const normalizedQuery = normalizeForMatch(queryString);
  const firstChar = normalizedQuery.charAt(0);

  return items.sort((a, b) => {
    const displayA = getDisplayText(a) || '';
    const displayB = getDisplayText(b) || '';

    const getMatchScore = (itemDisplay) => {
      const normalized = normalizeForMatch(itemDisplay);
      const words = normalized.split(/\s+/).filter(Boolean);
      const firstWord = words[0] || '';

      // Highest priority: query matches the beginning of the first name/word
      if (firstWord.startsWith(normalizedQuery)) return 120;

      // Next: full name starts with the query
      if (normalized.startsWith(normalizedQuery)) return 110;

      // Then: first word starts with the first character of the query
      if (firstChar && firstWord.startsWith(firstChar)) return 100;

      // Then: whole name starts with the first character of the query
      if (firstChar && normalized.startsWith(firstChar)) return 90;

      // Then: any word starts with the query
      if (words.some(word => word.startsWith(normalizedQuery))) return 80;

      // Then: the query appears anywhere in the text
      if (normalized.includes(normalizedQuery)) return 70;

      return 50;
    };

    const scoreA = getMatchScore(displayA);
    const scoreB = getMatchScore(displayB);

    return scoreB - scoreA;
  });
};

const applyPrescriptionPatient = (patient) => {
  if (!patient || typeof patient !== 'object') {
    return;
  }

  isNewPatient.value = false;
  isNewPatientFlag.value = false;

  if (patient.id) {
    patientForm.value.patient_id = patient.id;
  }

  patientSearchQuery.value = patient.name ?? patientSearchQuery.value;
  patientForm.value.patientMobile = patient.phone ?? patientForm.value.patientMobile;
  patientForm.value.gender = patient.gender ?? patientForm.value.gender;
  patientForm.value.dob = patient.dob ?? patientForm.value.dob;
  calculateAgeFromDOB(patientForm.value.dob);
};

const addPrescriptionTestsToItems = (tests = []) => {
  const availableTests = props.pathologyAndRadiologyTests.map((test) => ({
    id: test.id,
    name: String(test.test_name ?? '').trim(),
    category: String(test.category_type ?? 'Pathology').trim(),
    unitPrice: Number(test.amount ?? 0),
    referral_percentage: test.referral_percentage ?? 0,
  }));

  const existingKeys = new Set(
    items.value
      .filter((item) => String(item.category ?? '').toLowerCase() !== 'medicine')
      .map((item) => `${normalizeText(item.name)}::${normalizeText(item.category)}`)
  );

  let addedCount = 0;
  let skippedCount = 0;

  tests.forEach((rawName) => {
    const targetName = String(rawName ?? '').trim();
    if (!targetName) {
      return;
    }

    const matched = availableTests.find((test) => normalizeText(test.name) === normalizeText(targetName));
    if (!matched) {
      skippedCount += 1;
      return;
    }

    const uniqueKey = `${normalizeText(matched.name)}::${normalizeText(matched.category)}`;
    if (existingKeys.has(uniqueKey)) {
      skippedCount += 1;
      return;
    }

    items.value.push({
      id: matched.id,
      name: matched.name,
      category: matched.category,
      unitPrice: matched.unitPrice,
      roomNo: matched.room_no || '',
      referral_percentage: matched.referral_percentage ?? 0,
      quantity: 1,
      totalAmount: matched.unitPrice,
      discount: 0,
      rugound: 0,
      netAmount: matched.unitPrice,
    });

    existingKeys.add(uniqueKey);
    addedCount += 1;
  });

  if (addedCount > 0) {
    updateSummary();
  }

  return { addedCount, skippedCount };
};

const searchPrescriptionCharge = async () => {
  const trimmedId = String(prescriptionSearchId.value ?? '').trim();
  if (!trimmedId) {
    displayWarning({ message: 'Prescription ID দিন।' });
    return;
  }

  prescriptionSearchLoading.value = true;

  try {
    const endpoint = route('backend.billing.prescriptions.search');
    const url = `${endpoint}?prescription_id=${encodeURIComponent(trimmedId)}`;
    const response = await fetch(url, {
      headers: {
        Accept: 'application/json',
      },
    });

    const payload = await response.json();

    if (!response.ok) {
      displayWarning({ message: payload?.message || 'Prescription পাওয়া যায়নি।' });
      return;
    }

    applyPrescriptionPatient(payload?.patient);
    const { addedCount = 0, skippedCount = 0 } = addPrescriptionTestsToItems(payload?.tests || []) || {};

    if (addedCount === 0) {
      displayWarning({ message: 'Prescription থেকে নতুন কোনো test add করা যায়নি।' });
      return;
    }

    const source = payload?.source ? ` (${payload.source})` : '';
    const skipText = skippedCount > 0 ? `, ${skippedCount} skip` : '';
    displayResponse({ message: `${addedCount}টি test auto add হয়েছে${source}${skipText}.` });

    // Reset input after successful load for faster next scan/search.
    prescriptionSearchId.value = '';
    prescriptionSuggestions.value = [];
    showPrescriptionSuggestions.value = false;
    nextTick(() => {
      prescriptionSearchInputRef.value?.focus();
    });
  } catch (error) {
    // console.error removed
    displayWarning({ message: 'Prescription search failed. আবার চেষ্টা করুন।' });
  } finally {
    prescriptionSearchLoading.value = false;
  }
};

const fetchPrescriptionSuggestions = debounce(async (query) => {
  const normalized = String(query ?? '').trim();
  if (!normalized) {
    prescriptionSuggestions.value = [];
    showPrescriptionSuggestions.value = false;
    return;
  }

  prescriptionSuggestionLoading.value = true;
  try {
    const endpoint = route('backend.billing.prescriptions.suggest');
    const response = await fetch(`${endpoint}?search=${encodeURIComponent(normalized)}`, {
      headers: {
        Accept: 'application/json',
      },
    });

    const payload = await response.json();
    if (!response.ok || !Array.isArray(payload)) {
      prescriptionSuggestions.value = [];
      showPrescriptionSuggestions.value = false;
      return;
    }

    prescriptionSuggestions.value = payload;
    showPrescriptionSuggestions.value = payload.length > 0;
  } catch (error) {
    // console.error removed
    prescriptionSuggestions.value = [];
    showPrescriptionSuggestions.value = false;
  } finally {
    prescriptionSuggestionLoading.value = false;
  }
}, 250);

const handlePrescriptionInput = () => {
  fetchPrescriptionSuggestions(prescriptionSearchId.value);
};

const handlePrescriptionBlur = () => {
  setTimeout(() => {
    showPrescriptionSuggestions.value = false;
  }, 180);
};

const selectPrescriptionSuggestion = (suggestion) => {
  prescriptionSearchId.value = String(suggestion?.id ?? '').trim();
  showPrescriptionSuggestions.value = false;
  searchPrescriptionCharge();
};

const normalizeDiscountInputsByLimit = () => {
  const total = Number(summary.value.total) || 0;
  const vatAmount = billingVatEnabled.value && billingVatPercent.value > 0
    ? (total * billingVatPercent.value) / 100
    : 0;
  const totalWithVat = total + vatAmount;
  const maxPercent = Number(maxBillingDiscountPercent.value) || 0;

  if (totalWithVat <= 0) {
    summary.value.discount = 0;
    summary.value.extraFlatDiscount = 0;
    return;
  }

  const maxDiscountAmount = (totalWithVat * maxPercent) / 100;
  const discountType = summary.value.discountType;
  const rawDiscount = Math.max(0, Number(summary.value.discount) || 0);
  const rawExtra = Math.max(0, Number(summary.value.extraFlatDiscount) || 0);

  if (discountType === 'percentage') {
    const safePercent = Math.min(rawDiscount, maxPercent);
    const discountAmount = (total * safePercent) / 100;
    const allowedExtra = Math.max(0, maxDiscountAmount - discountAmount);

    if (summary.value.discount !== safePercent) {
      summary.value.discount = safePercent;
    }
    if (summary.value.extraFlatDiscount !== Math.min(rawExtra, allowedExtra)) {
      summary.value.extraFlatDiscount = Math.min(rawExtra, allowedExtra);
    }
    return;
  }

  const safeFlatDiscount = Math.min(rawDiscount, maxDiscountAmount);
  const allowedExtra = Math.max(0, maxDiscountAmount - safeFlatDiscount);

  if (summary.value.discount !== safeFlatDiscount) {
    summary.value.discount = safeFlatDiscount;
  }
  if (summary.value.extraFlatDiscount !== Math.min(rawExtra, allowedExtra)) {
    summary.value.extraFlatDiscount = Math.min(rawExtra, allowedExtra);
  }
};

const totalWithVat = computed(() => {
  return parseFloat((summary.value.total + summary.value.vatAmount).toFixed(2));
});

const updateSummary = () => {
  const total = items.value.reduce((sum, item) => sum + item.totalAmount, 0);
  summary.value.total = parseFloat(total.toFixed(2));
  normalizeDiscountInputsByLimit();

  summary.value.vatPercentage = billingVatEnabled.value ? billingVatPercent.value : 0;
  summary.value.vatAmount = billingVatEnabled.value && billingVatPercent.value > 0
    ? parseFloat((summary.value.total * summary.value.vatPercentage / 100).toFixed(2))
    : 0;

  const amountToDiscount = totalWithVat.value;
  let discountAmount = 0;

  if (summary.value.discountType === "percentage") {
    discountAmount = (amountToDiscount * summary.value.discount) / 100;
  } else {
    discountAmount = parseFloat(summary.value.discount) || 0;
  }

  const totalDiscountAmount = discountAmount + parseFloat(summary.value.extraFlatDiscount || 0);
  const finalDiscountAmount = Math.min(totalDiscountAmount, amountToDiscount);

  items.value = items.value.map((item) => {
    return {
      ...item,
      discount: 0,
      netAmount: item.totalAmount,
    };
  });

  summary.value.payableAmount = Math.max(
    0,
    parseFloat((amountToDiscount - finalDiscountAmount).toFixed(2))
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
  // ensure a sensible fallback category if the item lacks one
  if (!itemForm.value.category || String(itemForm.value.category).trim() === '') {
    if (item.type === 'medicine') itemForm.value.category = 'Medicine';
    else if (item.type === 'test') itemForm.value.category = item.category || 'Pathology';
    else itemForm.value.category = item.category || 'Service';
  }
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
    try {
      addItem();
    } catch (e) {
      console.warn('[BillingPage] addItem on Enter error', e);
    }
  }
};

// Set the current item category and reset item selection state
const setCategory = (category) => {
  const cat = String(category ?? '').trim();
  itemForm.value.category = cat;
  // Reset item-specific fields when category changes
  itemForm.value.itemName = '';
  itemForm.value.itemId = null;
  itemForm.value.unitPrice = 0;
  selectedIndex.value = -1;
  nextTick(() => {
    try { itemNameInput.value && itemNameInput.value.focus(); } catch (e) {}
  });
};

// Debounced setter to reduce recomputation while user types rapidly
const debouncedSetSearchQuery = debounce((val) => {
  console.log('[BillingPage] debouncedSetSearchQuery ->', val);
  searchQuery.value = val;

  nextTick(() => {
    try {
      if (filteredItems.value && filteredItems.value.length > 0) {
        selectedIndex.value = 0;

        // Do not auto-change category while typing — leave selection to the user.
      }
    } catch (e) {
      console.warn('[BillingPage] auto-select category error', e);
    }
  });
}, 180);

// Handle typing in the item name input: update debounced search query
const handleItemInput = (event) => {
  const q = String(event.target.value ?? '').trim();
  console.log('[BillingPage] handleItemInput ->', q);
  selectedIndex.value = -1;

  if (q === '') {
    try { debouncedSetSearchQuery.cancel && debouncedSetSearchQuery.cancel(); } catch (e) {}
    // Do not clear selected category when input is emptied by user — preserve category
    itemForm.value.itemName = '';
    itemForm.value.itemId = null;
    itemForm.value.unitPrice = 0;
    searchQuery.value = '';
    selectedIndex.value = -1;
    return;
  }

  debouncedSetSearchQuery(q);
};

// Debug: watch searchQuery to see when it updates
watch(searchQuery, (val) => {
  console.log('[BillingPage] watch searchQuery ->', val, 'category:', itemForm.value.category);
});

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

    const lastPaymentType = props.editData.payment_type || props.editData.pay_mode || "Cash";

    patientForm.value = {
      patient_id: props.editData.patient_id || "",
      doctor_id: props.editData.doctor_id || "",
      patientMobile: props.editData.patient_mobile || "",
      gender: props.editData.gender || "",
      dob: props.editData.dob || "",
      cardType: props.editData.card_type || lastPaymentType || "Cash",
      payMode: lastPaymentType,
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

    billingDateTouched.value = true;

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
      items.value = props.editData.items.map((item) => {
        // try to find item-level referral percentage from available props
        let refPercent = 0;
        try {
          const foundTest = (props.pathologyAndRadiologyTests || []).find(t => t.id == item.id);
          if (foundTest) refPercent = foundTest.referral_percentage ?? 0;
          else {
            const foundMed = (props.medicineInventories || []).find(m => m.id == item.id);
            if (foundMed) refPercent = foundMed.referral_percentage ?? 0;
            else refPercent = item.referral_percentage ?? 0;
          }
        } catch (e) {
          refPercent = item.referral_percentage ?? 0;
        }

        return {
          id: item.id,
          name: item.name,
          category: item.category,
          unitPrice: parseFloat(item.unit_price),
          quantity: parseFloat(item.quantity),
          totalAmount: parseFloat(item.total_amount),
          discount: item.discount || 0,
          rugound: item.rugound || 0,
          netAmount: parseFloat(item.net_amount),
          referral_percentage: refPercent,
          roomNo: item.room_no ?? item.roomNo ?? '',
        };
      });

    // Fallback: if user selected 'disposable' but nothing matched, do a broader scan
    try {
      if (selectedCategory.includes('dispos') && itemsToFilter.length === 0) {
        const fallback = allAvailableItems.value.filter(it => {
          try {
            const lname = String(it.name || '').toLowerCase();
            const lalt = String(it.alt || '').toLowerCase();
            if (lname.includes('dispos') || lalt.includes('dispos') || /ডিসপ/.test(lname) || /ডিসপ/.test(lalt)) return true;

            // inspect any string/number property for the substring
            const anyStringMatch = Object.values(it || {}).some(v => (typeof v === 'string' || typeof v === 'number') && /dispos|ডিসপ/i.test(String(v)));
            if (anyStringMatch) return true;

            // inspect keys: if a property name includes 'dispos' and its value is truthy (covers boolean flags)
            const anyKeyFlag = Object.keys(it || {}).some(k => /dispos|ডিসপ/i.test(String(k)) && Boolean(it[k]));
            if (anyKeyFlag) return true;

            return false;
          } catch (e) { return false; }
        });
        if (fallback.length > 0) {
          console.log('[BillingPage] disposable fallback matched', { count: fallback.length, sample: fallback.slice(0,8) });
          itemsToFilter = fallback;
        }
      }
    } catch (e) { console.warn('[BillingPage] disposable fallback failed', e); }
    // Broad token-based fallback: if no items matched the selected category,
    // but the user has typed tokens, search across all items by those tokens.
    try {
      if ((itemsToFilter || []).length === 0 && tokens.length > 0) {
        const tokenMatches = allAvailableItems.value.filter((item) => {
          try {
            const hay = ((item.name || '') + ' ' + (item.alt || '')).toLowerCase();
            return tokens.every((tok) => hay.includes(tok));
          } catch (e) { return false; }
        });
        if (tokenMatches.length > 0) {
          console.log('[BillingPage] token-broad fallback used', { selectedCategory, tokens, count: tokenMatches.length, sample: tokenMatches.slice(0,8) });
          itemsToFilter = tokenMatches;
        }
      }
    } catch (e) { console.warn('[BillingPage] token-broad fallback failed', e); }
    }

    const originalPaidAmount = parseFloat(props.editData.paid_amt || 0);
    const existingReturnAmount = parseFloat(props.editData.return_amt || 0);
    const payableAmount = parseFloat(props.editData.payable_amount || 0);
    const grossReceived = Math.max(0, originalPaidAmount - existingReturnAmount);
    const effectivePaid = Math.min(payableAmount, grossReceived);
    const computedDueAmount = Math.max(0, payableAmount - effectivePaid);

    // When editing, receivingAmount should always start at 0 (empty)
    // The original paid amount is tracked separately in calculateChangeAndDue()
    // as originalPaidAmount from editData.paid_amt
    // Only when user explicitly enters a new amount should receivingAmount change
    let receivingAmount = "";

    summary.value = {
      total: parseFloat(props.editData.total || 0),
      discount: parseFloat(props.editData.discount || 0),
      discountType: props.editData.discount_type || "flat",
      extraFlatDiscount: parseFloat(props.editData.extra_flat_discount || 0),
      vatPercentage: parseFloat(props.editData.vat_percentage || 0),
      vatAmount: parseFloat(props.editData.vat_amount || 0),
      payableAmount: payableAmount,
      paidAmt: effectivePaid,
      changeAmt: parseFloat(props.editData.change_amt || 0),
      dueAmount: computedDueAmount,
      receivingAmt: 0,
      returnAmt: existingReturnAmount,
      deliveryDate: props.editData.delivery_date || "",
      deliveryTime: props.editData.delivery_time || "",
      remarks: props.editData.remarks || "",
    };

    // Normalize deliveryDate into a `datetime-local` friendly format
    try {
      if (summary.value.deliveryDate) {
        const parsedDelivery = new Date(summary.value.deliveryDate);
        if (isValid(parsedDelivery)) {
          summary.value.deliveryDate = format(parsedDelivery, "yyyy-MM-dd'T'HH:mm");
          summary.value.deliveryTime = format(parsedDelivery, 'HH:mm');
        }
      }
    } catch (e) { /* ignore */ }

    commission.value = {
      total: parseFloat(props.editData.commission_total || 0),
      physystAmt: parseFloat(props.editData.physyst_amt || 0),
      slider: parseInt(props.editData.commission_slider || 0),
      referrer_id: props.editData.referrer_id || "",
      commissionRate: parseInt(props.editData.commission_slider || 0),
    };

    // Preserve existing invoice billing date/time in edit mode.
    let initialBillingDate = String(props.editData.billing_date || '').trim();
    let initialBillingTime = String(props.editData.billing_time || '').trim();
    if (!initialBillingDate && props.editData.created_at) {
      const parsedCreatedAt = parseBillingSourceDateTime(props.editData.created_at);
      if (parsedCreatedAt) {
        initialBillingDate = format(parsedCreatedAt, 'yyyy-MM-dd');
        if (!initialBillingTime) {
          initialBillingTime = format(parsedCreatedAt, 'HH:mm:ss');
        }
      }
    }
    billingDate.value = initialBillingDate;
    billingTime.value = initialBillingTime;
    billingDateTouched.value = true;
  }
};

const saveBill = (backendInvoice = false) => {
  console.log('=== SAVE BILL DEBUG ===');
  console.log('isNewPatient:', isNewPatient.value);
  console.log('patientForm.patient_id:', patientForm.value.patient_id);
  console.log('patientSearchQuery:', patientSearchQuery.value);

  if (!hasOpenCashCounterSession.value) {
    displayWarning({ message: 'কাউন্টার ক্লোজ আছে। বিল করার আগে Counter Start করুন।' });
    return;
  }

  // Defensive: if this function was used as an event handler without
  // parentheses (e.g. @click="saveBill"), the click Event object may be
  // passed as the first argument. Treat Event objects as falsy for our
  // backendInvoice flag so Save & Print flow still opens the invoice.
  if (backendInvoice && typeof backendInvoice === 'object' && backendInvoice instanceof Event) {
    backendInvoice = false;
  }

  if (items.value.length === 0) {
    displayWarning({ message: "Please add at least one item to the bill." });
    itemNameInput.value?.focus();
    return;
  }

  if (summary.value.payableAmount <= 0) {
    displayWarning({ message: "Payable amount must be greater than 0." });
    return;
  }

  // Recalculate payment status based on current summary values
  // When editing and adding items, ensure due_amount is properly reflected
  const dueAmount = parseFloat(summary.value.dueAmount) || 0;
  const paidAmount = parseFloat(summary.value.paidAmt) || 0;
  const payableAmount = parseFloat(summary.value.payableAmount) || 0;
  
  let paymentStatus = "Pending";
  
  if (dueAmount <= 0 && paidAmount >= payableAmount) {
    if (summary.value.returnAmt > 0) {
      paymentStatus = "Partial";
    } else {
      paymentStatus = "Paid";
    }
  } else if (paidAmount > 0 && dueAmount > 0) {
    paymentStatus = "Partial";
  } else if (dueAmount > 0) {
    paymentStatus = "Pending";
  }

  const itemsForBackend = items.value.map((item) => ({
    id: item.id,
    name: item.name,
    category: (() => {
      const cat = item.category && String(item.category).trim();
      if (cat) return cat;
      // try by id
      try {
        const byId = allAvailableItems.value.find(i => i.id === item.id);
        if (byId && byId.category) return byId.category;
      } catch (e) {}
      // try by normalized name match
      try {
        const n = normalizeForMatch(item.name || '');
        const byName = (allAvailableItems.value || []).find(i => normalizeForMatch(i.name || '') === n);
        if (byName && byName.category) return byName.category;
      } catch (e) {}
      // fallback
      return 'Service';
    })(),
    unit_price: item.unitPrice,
    quantity: item.quantity,
    total_amount: item.totalAmount,
    discount: item.discount,
    rugound: item.rugound || 0,
    net_amount: item.netAmount,
    room_no: item.room_no ?? item.roomNo ?? null,
  }));

  try {
    console.log('itemsForBackend payload:', JSON.parse(JSON.stringify(itemsForBackend || [])));
    try { console.log('itemsForBackend payload (string):', JSON.stringify(itemsForBackend || [])); } catch(e) {}
  } catch (e) {
    console.log('itemsForBackend payload (stringify failed):', itemsForBackend);
  }

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
    vat_percentage: summary.value.vatPercentage || 0,
    vat_amount: summary.value.vatAmount || 0,
    change_amt: summary.value.changeAmt || 0,
    due_amount: summary.value.dueAmount || 0,
    receiving_amt: summary.value.receivingAmt || 0,
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

  // Include billing date/time so backend can use it as the bill's created_at
  let billingTimeValue = String(billingTime.value || '').trim();
  if (!billingTimeValue && props.id && props.editData) {
    billingTimeValue = String(props.editData.billing_time || '').trim();
    if (!billingTimeValue && props.editData.created_at) {
      const backupParsed = parseBillingSourceDateTime(props.editData.created_at);
      if (backupParsed) {
        billingTimeValue = format(backupParsed, 'HH:mm:ss');
      }
    }
  }
  if (/^\d{2}:\d{2}$/.test(billingTimeValue)) {
    billingTimeValue = `${billingTimeValue}:00`;
  }
  formData.billing_date = billingDate.value || null;
  formData.billing_time = billingTimeValue || null;

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

  // include flag when saving as backend invoice
  if (backendInvoice) {
    formData.backend_invoice = true;
  }

  // If this is Save & Print flow, open a blank tab first so the later
  // invoice navigation is still treated as a user-initiated popup.
  let invoiceWindow = null;
  if (!backendInvoice) {
    try {
      invoiceWindow = window.open('about:blank', '_blank');
      try { if (invoiceWindow) invoiceWindow.opener = null; } catch (e) { /* ignore */ }
    } catch (e) {
      invoiceWindow = null;
    }

    const token = (typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : 'pt_' + Date.now();
    formData.print_token = token;

    let previewUrl = '';
    try {
      previewUrl = route('backend.download.invoice', { print_token: token, module: 'billing', fast_open: 1, auto_print: 1 });
    } catch (e) {
      previewUrl = `${window.location.origin}/download-invoice?print_token=${encodeURIComponent(token)}&module=billing&fast_open=1&auto_print=1`;
    }

    if (invoiceWindow) {
      try {
        invoiceWindow.location.href = previewUrl;
      } catch (e) {
        try { window.open(previewUrl, '_blank'); } catch (ee) { /* ignore */ }
      }
    } else {
      try { window.open(previewUrl, '_blank'); } catch (e) { /* ignore */ }
    }
  }
  console.log('invoiceWindow opened (or null):', invoiceWindow);

  const form = useForm(formData);

    const submitOptions = {
      onSuccess: (response) => {
        console.log('Save bill success:', response);
        reloadReferrers();

        const flashSuccessMessage = response?.message || response?.data?.message || response?.props?.flash?.successMessage || response?.props?.flash?.message || null;

        // Prefer direct invoiceUrl from server if provided
        let billId = null;
        const fastInvoiceHtml = typeof response?.fastInvoiceHtml === 'string' ? response.fastInvoiceHtml : '';
        if (!backendInvoice && fastInvoiceHtml && invoiceWindow && !invoiceWindow.closed) {
          try {
            invoiceWindow.document.open('text/html', 'replace');
            invoiceWindow.document.write(fastInvoiceHtml);
            invoiceWindow.document.close();
            invoiceWindow.focus();
          } catch (e) {
            // keep existing fallback URL navigation below
          }
        }

        if (response?.invoiceUrl) {
          try {
            const providedUrl = String(response.invoiceUrl || '');
            // navigate immediately
            if (!backendInvoice) {
              if (!fastInvoiceHtml) {
                try { if (invoiceWindow && !invoiceWindow.closed) { invoiceWindow.location.href = providedUrl; invoiceWindow.focus(); } else { window.open(providedUrl, '_blank'); } } catch (e) { try { window.open(providedUrl, '_blank'); } catch (ee) { /* ignore */ } }
              }
            }
          } catch (err) { /* ignore */ }
        }
        if (response?.billId) billId = response.billId;
        if (!billId && response?.data?.billId) billId = response.data.billId;
        if (!billId && response?.data?.data?.billId) billId = response.data.data.billId;
        if (!billId && response?.props?.flash?.billId) billId = response.props.flash.billId;
        if (!billId && response?.props?.billId) billId = response.props.billId;
        if (!billId && typeof response === 'string') {
          try { const parsed = JSON.parse(response); if (parsed?.billId) billId = parsed.billId; } catch (e) { }
        }

        console.log('onSuccess: extracted billId=', billId, 'backendInvoice=', backendInvoice, 'invoiceWindow=', invoiceWindow);
        successMessage(flashSuccessMessage || 'Billing saved successfully.');

        // Dispatch dashboard refresh event to update Net Income card
        try {
          localStorage.setItem('dashboard:refresh', String(Date.now()));
          window.dispatchEvent(new Event('dashboard:refresh'));
          // Also notify billing list pages to refresh their data without a full reload
          try {
            localStorage.setItem('billing:list:refresh', String(Date.now()));
            window.dispatchEvent(new Event('billing:list:refresh'));
          } catch (innerErr) {
            // non-fatal
          }
        } catch (e) {
          console.warn('Failed to dispatch dashboard refresh event:', e);
        }

        // Keep the current billing page open after save so the user stays on the same tab.
        // The invoice opens in a separate tab and the billing screen should not redirect away.
        if (billId) {
          const isEditingExistingBill = Boolean(props.id && props.editData);
          if (!isEditingExistingBill) {
            resetAllForms();
          } else {
            isEditMode.value = true;
            billingDateTouched.value = true;
            nextTick(() => {
              deliveryDateTouched.value = !!summary.value.deliveryDate;
            });
          }

          if (!backendInvoice) {
            let invoiceUrl = '';
            try { invoiceUrl = route ? route('backend.download.invoice', { id: billId, module: 'billing', fast_open: 1, auto_print: 1 }) : ''; } catch (e) { invoiceUrl = ''; }
            if (!invoiceUrl) {
              try { invoiceUrl = `${window.location.origin}/download-invoice?id=${encodeURIComponent(billId)}&module=billing&fast_open=1&auto_print=1`; } catch (e) { invoiceUrl = '/download-invoice?id=' + encodeURIComponent(billId) + '&module=billing&fast_open=1&auto_print=1'; }
            }
            invoiceUrl = String(invoiceUrl || '');

            try {
              if (!fastInvoiceHtml) {
                if (invoiceWindow && !invoiceWindow.closed) {
                  try { invoiceWindow.location.href = invoiceUrl; invoiceWindow.focus(); } catch (err) { window.open(invoiceUrl, '_blank'); }
                } else {
                  window.open(invoiceUrl, '_blank');
                }
              }
            } catch (e) {
              if (!fastInvoiceHtml) {
                try { window.open(invoiceUrl, '_blank'); } catch (ee) { }
              }
            }

            // Keep current billing page state even after invoice tab is closed.
            if (invoiceMonitorTimer) {
              clearInterval(invoiceMonitorTimer);
              invoiceMonitorTimer = null;
            }
          }
        } else {
          if (!props.id) resetAllForms();
          console.warn('Bill ID not found in response; invoice tab cannot be navigated automatically.');
        }
      },
      onError: (errors) => {
        console.error('Save bill errors:', errors);
        try { if (invoiceWindow && !invoiceWindow.closed) invoiceWindow.close(); } catch (e) { }

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
          const errorMessage = typeof errors === 'string' ? errors : 'Please check the form for errors and try again.';
          displayWarning({ message: errorMessage });
        }
      },
      onFinish: () => {
        console.log('Save bill request finished');
      }
    };

    let submitUrl = '';
    try {
      submitUrl = props.id ? route("backend.billing.update", { billing: props.id }) : route("backend.billing.store");
      // defensive: if route() returned a generic page URL (or a wrong mapping like
      // `view-billing-list-page`), fallback below to the expected REST endpoint.
      if (
        typeof submitUrl !== 'string' ||
        submitUrl.indexOf('/') === -1 ||
        submitUrl.includes('view-billing-page') ||
        submitUrl.includes('view-billing-list-page') ||
        submitUrl.includes('backend.billing.update')
      ) {
        throw new Error('invalid route');
      }
    } catch (e) {
      submitUrl = props.id ? `/billing/${props.id}` : '/billing';
    }

    // Always use AJAX for both Save and Save & Print to avoid page redirect
    // Send raw AJAX request (axios) so server JSON won't be intercepted by Inertia
    const method = props.id ? 'put' : 'post';
    axios({ method, url: submitUrl, data: formData })
      .then((res) => {
        const payload = res?.data ?? res;
        if (typeof payload === 'string' && /<!doctype html|<html/i.test(payload)) {
          throw { response: { data: { errors: 'Billing save failed: server returned an HTML response instead of JSON.' } } };
        }
        if (payload?.success === false) {
          throw { response: { data: { errors: payload?.errors || payload?.message || 'Billing save failed.' } } };
        }
        submitOptions.onSuccess(payload);
      })
      .catch((err) => {
        const errors = err.response?.data?.errors ?? err.response?.data ?? err;
        submitOptions.onError(errors);
      })
      .finally(() => {
        submitOptions.onFinish();
      });
};

const shouldPreventPageRefresh = () => {
  // Prevent page refresh when saving - always stay on the edit page or list
  // unless explicitly navigating away
  return true;
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
    discountType: "flat",
    extraFlatDiscount: 0,
    payableAmount: 0,
    paidAmt: 0,
    changeAmt: 0.0,
    dueAmount: 0.0,
    receivingAmt: 0.0,
    returnAmt: 0.0,
    // Default delivery date/time: set a delivery slot based on the current hour.
    // Example: 2:00-2:59 -> 8:00, 3:00-3:59 -> 9:00, and so on.
    deliveryDate: (function(){ const value = getDefaultDeliveryDateTime(); return value.date; })(),
    deliveryTime: (function(){ const value = getDefaultDeliveryDateTime(); return value.time; })(),
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

  referrerSearch.value = '';
  showReferrerDropdown.value = false;
  highlightedRefIndex.value = 0;
  filteredReferrers.value = [];
  submitOnNextReferrerEnter.value = false;
};

// Delivery datetime helpers.
// Default to a delivery slot six hours ahead of the current hour (e.g. 2:00 -> 8:00, 3:00 -> 9:00).
const getDefaultDeliveryDateTime = (baseDate = new Date()) => {
  try {
    const now = baseDate instanceof Date ? new Date(baseDate) : new Date();
    const currentHour = now.getHours();
    const deliveryHour = currentHour + 6;
    const deliveryDate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), deliveryHour, 0, 0);

    if (deliveryHour >= 24) {
      deliveryDate.setDate(deliveryDate.getDate() + 1);
      deliveryDate.setHours(deliveryHour - 24);
    }

    return {
      date: format(deliveryDate, "yyyy-MM-dd'T'HH:mm"),
      time: format(deliveryDate, 'HH:mm'),
    };
  } catch (e) {
    console.warn('[BillingPage] getDefaultDeliveryDateTime error', e);
    const fallback = new Date();
    return {
      date: format(fallback, "yyyy-MM-dd'T'HH:mm"),
      time: format(fallback, 'HH:mm'),
    };
  }
};

const setCurrentDeliveryDateTime = () => {
  if (summary.value.deliveryDate) return;
  try {
    const defaultDelivery = getDefaultDeliveryDateTime();
    summary.value.deliveryDate = defaultDelivery.date;
    summary.value.deliveryTime = defaultDelivery.time;
  } catch (e) { console.warn('[BillingPage] setCurrentDeliveryDateTime error', e); }
};

const startDeliveryLiveClock = () => {
  if (deliveryLiveTimer) return;
  deliveryLiveTimer = setInterval(() => { if (!deliveryDateTouched.value) setCurrentDeliveryDateTime(); }, 60 * 1000);
};

const ensureDeliveryDateTime = () => {
  deliveryDateTouched.value = true;
  if (!summary.value.deliveryDate) return setCurrentDeliveryDateTime();
  try {
    const parsed = new Date(summary.value.deliveryDate);
    if (isValid(parsed)) {
      summary.value.deliveryDate = format(parsed, "yyyy-MM-dd'T'HH:mm");
      summary.value.deliveryTime = format(parsed, 'HH:mm');
    }
  } catch (e) { console.warn('[BillingPage] ensureDeliveryDateTime error', e); }
};

const handleDeliveryDateInput = () => {
  deliveryDateTouched.value = true;
  try { const parsed = new Date(summary.value.deliveryDate); if (isValid(parsed)) summary.value.deliveryTime = format(parsed, 'HH:mm'); } catch (e) {}
};

watch(billingDate, (newBillingDate) => {
  if (!newBillingDate || deliveryDateTouched.value) return;

  try {
    const time = summary.value.deliveryTime || '19:00';
    const parsed = new Date(`${newBillingDate}T${time}`);
    if (isValid(parsed)) {
      summary.value.deliveryDate = format(parsed, "yyyy-MM-dd'T'HH:mm");
      summary.value.deliveryTime = format(parsed, 'HH:mm');
    }
  } catch (e) { /* ignore */ }
});

const handlePatientSearchBlur = (event) => {
  setTimeout(() => {
    showPatientDropdown.value = false;
  }, 200);
};

const cancelBill = () => {
  router.visit(route("backend.billing.Page"));
};

const openListBillButton = () => {
  try {
    const url = route("backend.billing.list");
    if (url) {
      window.location.assign(url);
      return;
    }
  } catch (e) {
    // fallback when named route resolution fails
  }
  window.location.assign('/view-billing-list-page');
};

const openAddBillButton = () => {
  try {
    const url = route("backend.billing.view");
    if (url) {
      window.location.assign(url);
      return;
    }
  } catch (e) {
    // fallback when named route resolution fails
  }
  window.location.assign('/view-billing-page');
};

// Initialize edit mode if needed
onMounted(() => {
  if (props.id && props.editData) {
    initializeEditMode();
    nextTick(() => {
      deliveryDateTouched.value = !!summary.value.deliveryDate;
    });
  }

  startDeliveryLiveClock();
  if (!deliveryDateTouched.value) {
    setCurrentDeliveryDateTime();
  }

  startBillingLiveClock();
  startBillingClockTicker();
  if (!billingDateTouched.value) {
    setCurrentBillingDateTime();
  }
  // initialize referrer suggestions as hidden until user types
  filteredReferrers.value = [];

  // attach global listeners to close dropdowns on outside click or focus
  document.addEventListener('click', _onDocumentClick, true);
  document.addEventListener('focusin', _onDocumentFocusIn, true);

  // Startup debug: log category counts across sources to help troubleshoot missing categories
  try {
    const catCounts = {};
    (props.pathologyAndRadiologyTests || []).forEach(t => {
      const c = String(t.category_type || '').trim();
      if (!c) return;
      catCounts[c] = (catCounts[c] || 0) + 1;
    });
    (props.medicineInventories || []).forEach(m => {
      const c = String('Medicine').trim();
      catCounts[c] = (catCounts[c] || 0) + 1;
    });
    // hospitalCharges removed from aggregated sources

    console.debug('[BillingPage] startup category counts', catCounts);
  } catch (e) { console.warn('[BillingPage] startup category counts failed', e); }

  window.addEventListener('storage', handleDashboardStorageRefresh);
  window.addEventListener('storage', handleCloseInvoiceTabsStorage);
  window.addEventListener('dashboard:refresh', handleDashboardSameTabRefresh);
  window.addEventListener('focus', handleBillingWindowFocus);
  reloadNetIncomeValue();
});

onUnmounted(() => {
  // cleanup timers
  if (deliveryLiveTimer) {
    clearInterval(deliveryLiveTimer);
    deliveryLiveTimer = null;
  }
  if (billingLiveTimer) {
    clearInterval(billingLiveTimer);
    billingLiveTimer = null;
  }
  if (billingClockTimer) {
    clearInterval(billingClockTimer);
    billingClockTimer = null;
  }
  if (invoiceMonitorTimer) {
    clearInterval(invoiceMonitorTimer);
    invoiceMonitorTimer = null;
  }

  // remove document click listener
  try {
    document.removeEventListener('click', _onDocumentClick, true);
    document.removeEventListener('focusin', _onDocumentFocusIn, true);
  } catch (e) { /* ignore */ }

  // cleanup window listeners
  window.removeEventListener('storage', handleDashboardStorageRefresh);
  window.removeEventListener('storage', handleCloseInvoiceTabsStorage);
  window.removeEventListener('dashboard:refresh', handleDashboardSameTabRefresh);
  window.removeEventListener('focus', handleBillingWindowFocus);
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
}, quickSearchDebounceMs));

const searchDoctors = async (query) => {
  if (!query || query.trim().length < 1) return;

  isDoctorLoading.value = true;
  try {
    const response = await axios.get(route('backend.billing.doctors.search'), {
      params: { search: query }
    });

    // Apply smart sorting to results
    let filtered = response.data || [];
    filtered = smartSortSearchResults(filtered, query, (doctor) => doctor.name);
    filteredDoctors.value = filtered;

    const hasExactMatch = filteredDoctors.value.some(doctor =>
      doctor.name.toLowerCase() === query.trim().toLowerCase()
    );

    if (filteredDoctors.value.length > 0 && !hasExactMatch) {
      showDoctorDropdown.value = true;
      doctorSelectedIndex.value = 0;
    } else {
      showDoctorDropdown.value = false;
      doctorSelectedIndex.value = -1;
    }
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
      if (!patientForm.value.gender) {
        genderSelectRef.value?.focus();
        setTimeout(() => openDropdown(genderSelectRef), 100);
      } else if (!patientForm.value.dob) {
        dobInput.value?.focus();
      } else {
        ageYearsInput.value?.focus();
      }
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
  <div class="billing-page-text min-h-screen bg-gray-50 dark:bg-gray-900 overflow-y-auto">
    <div class="w-full p-2">
      <div class="bg-white rounded-lg shadow-lg dark:bg-slate-900 mb-4">
        <div class="mb-3">
          <div
            class="flex flex-wrap justify-between items-center bg-[#053855] text-white px-3 py-2 text-xs font-semibold rounded-t-lg gap-2">
            <div class="flex-1"></div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-2 text-white">
                  <!-- Display text normally; on hover show editable inputs -->
                  <div class="relative" @mouseenter="billingEditing = true" @mouseleave="billingEditing = false">
                      <template v-if="!billingEditing">
                      <span class="text-sm font-semibold text-white px-2">{{ billingLiveDisplay }}</span>
                    </template>
                    <template v-else>
                      <input
                        type="date"
                        v-model="billingDate"
                        class="px-2 py-1 border border-white/30 rounded text-[11px] text-white bg-white/10 focus:border-white focus:outline-none"
                        ref="billingDateRef"
                        @input="handleBillingDateTimeInput"
                      />
                      <input
                        type="time"
                        v-model="billingTime"
                        step="1"
                        class="px-2 py-1 border border-white/30 rounded text-[11px] text-white bg-white/10 focus:border-white focus:outline-none ml-2"
                        ref="billingTimeRef"
                        @input="handleBillingDateTimeInput"
                      />
                    </template>
                  </div>
                </div>
                <div class="flex items-center gap-2 flex-nowrap">
                  <div class="relative min-w-0">
                    <input
                      v-model="prescriptionSearchId"
                      ref="prescriptionSearchInputRef"
                      type="text"
                      class="w-[140px] md:w-[170px] px-2 py-1 border border-slate-200 rounded text-[11px] bg-white text-slate-900 placeholder-slate-600 focus:border-slate-400 focus:outline-none min-w-0"
                      placeholder="Prescription ID"
                      @input="handlePrescriptionInput"
                      @focus="handlePrescriptionInput"
                      @blur="handlePrescriptionBlur"
                      @keydown.enter.prevent="searchPrescriptionCharge"
                    />
                    <div
                      v-if="showPrescriptionSuggestions"
                      class="absolute right-0 left-0 mt-1 z-20 bg-white text-slate-700 border border-slate-200 rounded shadow max-h-56 overflow-y-auto"
                    >
                      <button
                        v-for="item in prescriptionSuggestions"
                        :key="`${item.source}-${item.id}`"
                        type="button"
                        class="w-full text-left px-2 py-1.5 text-[11px] hover:bg-slate-100 border-b border-slate-100"
                        @click="selectPrescriptionSuggestion(item)"
                      >
                        <span class="font-semibold">{{ item.label }}</span>
                        <span class="text-slate-500"> | {{ item.patient_name || 'Unknown' }}</span>
                      </button>
                    </div>
                    <div
                      v-if="prescriptionSuggestionLoading"
                      class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-white/70"
                    >
                      ...
                    </div>
                  </div>
                  <button
                    type="button"
                    class="px-2 py-1 rounded text-[11px] bg-emerald-500 hover:bg-emerald-600 text-white disabled:opacity-60 whitespace-nowrap"
                    :disabled="prescriptionSearchLoading"
                    @click="searchPrescriptionCharge"
                  >
                    {{ prescriptionSearchLoading ? 'Searching...' : 'Load' }}
                  </button>
                </div>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-md bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700 transition duration-200 shadow-sm"
                @click="goBack"
                title="Back"
              >
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back
              </button>
              <button
                type="button"
                class="flex items-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-md text-xs transition-colors duration-200 shadow-sm disabled:opacity-60"
                :disabled="cashCounterStartBusy"
                @click="openCounterStartModal"
                title="Start My Counter"
              >
                Counter Start
              </button>
              <button
                type="button"
                class="flex items-center px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-md text-xs transition-colors duration-200 shadow-sm disabled:opacity-60"
                :disabled="cashCounterCloseBusy"
                @click="openCounterCloseModal"
                title="Close My Counter"
              >
                Counter Close
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs transition hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-900/30 dark:hover:bg-emerald-900/50"
                title="Open Total Net Income Report"
                @click="openNetIncomeReport"
              >
                <span class="font-semibold text-white">Net Income</span>
                <span class="font-bold text-white">৳{{ formattedTotalNetIncome }}</span>
              </button>
              <a :href="route('backend.billing.view')" target="_blank"
                class="flex items-center px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md text-xs transition-colors duration-200 shadow-sm"
                style="color: #ffffff !important"
                title="Billing List">
                Billing Add
              </a>
              <a :href="route('backend.billing.list')" target="_blank"
                class="flex items-center px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-md text-xs transition-colors duration-200 shadow-sm"
                style="color: #ffffff !important"
                title="Billing List">
                Billing List
              </a>
            </div>
          </div>
          <div class="border border-gray-300 border-t-0 p-3 bg-gray-50 dark:bg-slate-800 dark:border-gray-600">
            <div class="flex items-center space-x-4 text-xs mb-3">
              <span class="font-medium text-gray-700 dark:text-gray-300"><strong>UNIT:</strong> {{ unitCompanyName }}</span>
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
                    <option v-for="type in categoryTypes" :key="type" :value="type">{{ type }}</option>
                  </select>
              </div>
              <div class="lg:col-span-4 relative">
                <InputLabel for="itemName" value="Item Name" class="text-xs mb-1" />
                <div class="relative">
                  <input v-model="itemForm.itemName" @input="handleItemInput($event)"
                    @keydown="handleKeyDown($event, 'itemName')" @focus="selectedIndex = -1" id="itemName" type="text"
                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    placeholder="Search items..." autocomplete="off" ref="itemNameInput" />
                  <div v-if="searchQuery || (itemForm.category && filteredItems.length > 0)"
                    class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto dark:bg-slate-700 dark:border-gray-600">
                    <ul>
                      <li v-for="(item, index) in filteredItems" :key="`${item.type}-${item.category}-${item.id}`" @click="selectItem(item)"
                        @keypress.enter="selectItem(item);" :class="['list-focus px-3 py-2 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600',
                          { 'dropdown-highlight-blue font-semibold': index === selectedIndex }]"
                        :ref="(el) => { if (index === selectedIndex) selectedItemRef = el }">
                        <div class="flex justify-between">
                          <span>{{ item.name }}</span>
                          <span :class="{ 'text-white': index === selectedIndex, 'text-gray-500 dark:text-gray-300': index !== selectedIndex }">
                            {{ item.type === "medicine" ? "Medicine" : item.category }}
                            (৳{{ item.unitPrice }})
                          </span>
                        </div>
                        <div v-if="item.type === 'medicine'" :class="['text-xs', { 'text-white': index === selectedIndex, 'text-gray-500 dark:text-gray-400': index !== selectedIndex }]">
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
          <div class="border border-gray-300 border-t-0">
            <div :style="itemsContainerStyle" class="">
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
                        'bg-amber-100 text-amber-800 px-2 py-1 rounded-full text-xs':
                          item.category === 'OPD',
                        'bg-cyan-100 text-cyan-800 px-2 py-1 rounded-full text-xs':
                          item.category === 'IPD',
                        'bg-rose-100 text-rose-800 px-2 py-1 rounded-full text-xs':
                          item.category === 'Appointment',
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
              <!-- Patient Mobile -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel for="patientMobile" value="Patient Mobile" class="text-xs" />
                <div class="col-span-2 relative" ref="patientMobileWrapperRef">
                  <input v-model="patientForm.patientMobile" type="text" id="patientMobile"
                    placeholder="Search name and mobile number"
                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    ref="patientMobileRef" @input="handlePatientMobileInput" @keydown="handlePatientMobileKeydown" />

                  <div v-if="showPatientMobileDropdown && patientMobileSuggestions.length > 0"
                    class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-48 overflow-auto dark:bg-slate-700 dark:border-gray-600">
                    <ul>
                      <li v-for="(patient, index) in patientMobileSuggestions" :key="patient.id"
                        @mousedown.prevent="selectPatientFromMobileSuggestion(patient)"
                        :class="[
                          'px-3 py-2 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600',
                          { 'dropdown-highlight-blue font-semibold': index === patientMobileSelectedIndex }
                        ]">
                        <div class="flex justify-between items-center gap-2">
                          <span class="font-medium">{{ patient.displayPhone }}</span>
                          <span :class="{ 'text-white': index === patientMobileSelectedIndex, 'text-gray-500 dark:text-gray-300': index !== patientMobileSelectedIndex }">{{ patient.displayName }}</span>
                        </div>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Patient Search -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel for="patient_id" value="Patient Name" class="text-xs" />
                <div class="col-span-2 relative">
                  <input v-model="patientSearchQuery" id="patientSearch" type="text"
                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    placeholder="Enter patient name" ref="patientSearchRef"
                    @keydown="handlePatientSearchKeyDown" />

                  <div v-if="false"
                    class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-48 overflow-auto dark:bg-slate-700 dark:border-gray-600">
                    <ul>
                      <li v-for="(patient, index) in filteredPatients" :key="patient.id"
                        @mousedown.prevent="selectPatient(patient)"
                        :class="[
                          'px-3 py-2 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600',
                          { 'dropdown-highlight-blue font-semibold': index === patientSelectedIndex }
                        ]">
                        <div class="flex justify-between items-center gap-2">
                          <span class="font-medium">{{ patient.name }}</span>
                          <span :class="{ 'text-white': index === patientSelectedIndex, 'text-gray-500 dark:text-gray-300': index !== patientSelectedIndex }">
                            {{ patient.phone }}
                          </span>
                        </div>
                      </li>
                    </ul>
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

                  <!-- Doctor dropdown list -->
                  <div v-if="showDoctorDropdown && filteredDoctors.length > 0"
                      class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto dark:bg-slate-700 dark:border-gray-600">
                    <ul>
                      <li v-for="(doctor, index) in filteredDoctors" 
                          :key="doctor.id" 
                          @mousedown.prevent="selectDoctor(doctor)" 
                          :class="[
                            'px-3 py-2 text-xs cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600',
                            { 'dropdown-highlight-blue font-semibold': index === doctorSelectedIndex }
                          ]">
                        <div class="flex justify-between items-center">
                          <span>{{ doctor.name }}</span>
                        </div>
                      </li>
                    </ul>
                  </div>


                </div>
              </div>

              <!-- Hidden field to store the selected doctor ID -->
              <input type="hidden" v-model="patientForm.doctor_id" />

              <!-- Gender -->
              <div class="grid grid-cols-3 gap-2 items-center">
                <InputLabel for="gender" value="Gender" class="text-xs" />
                <div class="col-span-2 relative" ref="genderWrapperRef">
                  <input 
                    v-model="patientForm.gender" 
                    id="gender"
                    type="text"
                    readonly
                    @focus="showGenderDropdown = true; genderSelectedIndex = genderOptions.findIndex(opt => opt.value === patientForm.gender)"
                    @click="toggleGenderDropdown"
                    @keydown="handleGenderEnter"
                    class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200 cursor-pointer"
                    ref="genderSelectRef" 
                    :placeholder="patientForm.gender ? genderOptions.find(opt => opt.value === patientForm.gender)?.label : 'Select'" />
                  
                  <!-- Gender dropdown list -->
                  <div v-if="showGenderDropdown"
                    class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg dark:bg-slate-700 dark:border-gray-600">
                    <ul>
                      <li v-for="(option, index) in genderOptions" 
                        :key="option.value"
                        @mousedown.prevent="selectGenderOption(option)"
                        @mouseenter="genderSelectedIndex = index"
                        :class="[
                          'px-3 py-2 text-xs cursor-pointer',
                          index === genderSelectedIndex
                            ? 'dropdown-highlight-blue font-semibold'
                            : 'dropdown-default-white'
                        ]">
                        {{ option.label }}
                      </li>
                    </ul>
                  </div>
                </div>
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
              <div v-if="billingVatEnabled" class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="vatAmount" :value="`VAT (${billingVatPercent.toFixed(2)}%)`" class="text-xs font-semibold" />
                <div class="flex">
                  <input v-model="summary.vatAmount" type="number" step="0.01" id="vatAmount" readonly
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs bg-gray-100 dark:bg-gray-600 dark:text-gray-200" />
                  <span
                    class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="total" value="Total Amount" class="text-xs font-semibold" />
                <div class="flex">
                  <input :value="summary.total.toFixed(2)" type="number" step="0.01" id="total" readonly
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs bg-gray-100 dark:bg-gray-600 dark:text-gray-200" />
                  <span
                    class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200">৳</span>
                </div>
              </div>
              <div v-if="billingVatEnabled" class="grid grid-cols-2 gap-2 items-center">
                <InputLabel for="totalWithVat" value="Total With VAT" class="text-xs font-semibold" />
                <div class="flex">
                  <input :value="totalWithVat.toFixed(2)" type="number" step="0.01" id="totalWithVat" readonly
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs bg-gray-100 dark:bg-gray-600 dark:text-gray-200" />
                  <span
                    class="px-2 py-1.5 bg-gray-200 border-t border-b border-r border-gray-300 rounded-r text-xs dark:bg-gray-600 dark:text-gray-200">৳</span>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-1">
                <div class="grid grid-cols-2 gap-1 items-center">
                  <InputLabel for="discount" value="Discount" class="text-xs" />
                  <select v-model="summary.discountType"
                    class="w-full min-w-max px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200 ml-[-1.5rem]"
                    style="-webkit-appearance:none; -moz-appearance:none; appearance:none; background-image:none;">
                    <option value="percentage">Percentage (%)</option>
                    <option value="flat">Flat Amount (৳)</option>
                  </select>
                </div>
                <div class="flex">
                  <input v-model="summary.discount" type="number" step="1" min="0" :max="summary.discountType === 'percentage' ? maxBillingDiscountPercent : null" id="discount"
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-l text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                    ref="discountRef" @keydown="handleDiscountEnter" @wheel="onDiscountWheel" @click="selectAllOnFocus" @focus="selectAllOnFocus" />
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
                    placeholder="Additional flat discount" ref="extraDiscountRef" @keydown="handleExtraDiscountEnter" @click="selectAllOnFocus" @focus="selectAllOnFocus" />
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
                <InputLabel for="receivingAmt" value="Receiving Amount"
                  class="text-xs font-semibold text-blue-700 dark:text-blue-400" />
                <div class="flex">
                  <input v-model="summary.receivingAmt" type="number" step="0.01" min="0" id="receivingAmt"
                    class="w-full px-2 py-1.5 border border-green-600 rounded-l text-xs focus:border-green-700 focus:outline-none bg-green-600 text-white placeholder-white/70 dark:bg-green-700 dark:border-green-500 dark:text-white"
                    placeholder="Amount given by customer" ref="receivingAmtRef" @keydown="handleReceivingAmtEnter" @click="selectAllOnFocus" @focus="selectAllOnFocus" />
                  <span
                    class="px-2 py-1.5 bg-green-700 border-t border-b border-r border-green-600 rounded-r text-xs font-semibold text-white dark:bg-green-800 dark:border-green-500 dark:text-white">৳</span>
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
                <InputLabel for="deliveryDate" value="Delivery Date and Time" class="text-xs" />
                <input v-model="summary.deliveryDate" type="datetime-local" id="deliveryDate"
                  class="px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                  ref="deliveryDateRef" @keydown="handleDeliveryDateEnter" @focus="ensureDeliveryDateTime"
                  @input="handleDeliveryDateInput" />
                <!-- delivery date display moved to invoice page -->
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
                  <div class="relative w-64">
                      <input v-model="referrerSearch" id="referrer_id"
                      class="w-full px-2 py-1.5 border border-gray-300 rounded text-xs focus:border-blue-500 focus:outline-none dark:bg-slate-700 dark:border-gray-600 dark:text-gray-200"
                      ref="referrerSelectRef" @keydown="handleReferrerKeydown"
                      @focus="handleReferrerFocus"
                      @blur="handleReferrerBlur"
                      placeholder="Search Referrer..." autocomplete="off" />
                    <ul v-if="showReferrerDropdown && filteredReferrers.length > 0" class="absolute left-0 right-0 z-50 mt-1 bg-white border border-gray-200 rounded shadow max-h-48 overflow-auto text-xs dark:bg-slate-700 dark:border-gray-600">
                      <li v-for="(r, idx) in filteredReferrers" :key="r.id" @mousedown.prevent="selectReferrer(r)" @mouseenter="highlightedRefIndex = idx"
                        :class="['px-2 py-1 cursor-pointer hover:bg-gray-100 dark:hover:bg-slate-600', (idx===highlightedRefIndex) ? 'dropdown-highlight-blue font-semibold' : '']">{{ r.name }} <span :class="(idx===highlightedRefIndex) ? 'text-white' : 'text-gray-400 dark:text-gray-300'"> - {{ r.phone }}</span></li>
                    </ul>
                  </div>
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
                  <InputLabel for="physystAmt" value="Commission Amt:" class="text-xs" />
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
                <button @click="saveBill()" @keydown.enter.prevent="saveBill()" ref="savePrintButtonRef"
                  :disabled="!hasOpenCashCounterSession"
                  class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm font-medium flex items-center space-x-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                  <span>💾</span>
                  <span>Save & Print Bill</span>
                </button>
                <button @click="saveBill(true)" @keydown.enter.prevent="saveBill(true)"
                  :disabled="!hasOpenCashCounterSession"
                  class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm font-medium flex items-center space-x-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                  <span>📥</span>
                  <span>Save</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showCounterStartModal" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b px-5 py-3">
          <h3 class="text-base font-semibold text-gray-800">Counter Start</h3>
          <button
            type="button"
            class="text-gray-500 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="cashCounterStartBusy"
            @click="closeCounterStartModal"
          >
            ✕
          </button>
        </div>

        <div class="px-5 py-4">
          <table class="w-full text-sm text-gray-700">
            <tr>
              <td class="py-1 font-semibold">User</td>
              <td class="py-1">{{ authInfo?.admin?.name || 'N/A' }}</td>
            </tr>
            <tr>
              <td class="py-1 font-semibold">Counter</td>
              <td class="py-1">{{ authInfo?.department?.name || 'N/A' }}</td>
            </tr>
          </table>

          <div class="mt-4">
            <label class="mb-1 block text-sm font-semibold text-gray-700">Opening Amount (Carry Cash)</label>
            <input
              v-model="counterStartForm.openingAmount"
              type="number"
              step="0.01"
              min="0"
              :disabled="cashCounterStartBusy"
              @keydown.enter.prevent="startCounterFromBilling"
              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
              placeholder="যেমন: 5000"
            >
          </div>

          <div class="mt-3">
            <label class="mb-1 block text-sm font-semibold text-gray-700">Note (optional)</label>
            <textarea
              v-model="counterStartForm.note"
              rows="2"
              :disabled="cashCounterStartBusy"
              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
              placeholder="Carry note"
            ></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t px-5 py-3">
          <button
            type="button"
            class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="cashCounterStartBusy"
            @click="closeCounterStartModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="cashCounterStartBusy"
            @click="startCounterFromBilling"
          >
            {{ cashCounterStartBusy ? 'Starting...' : 'Start Counter' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="showCounterCloseModal" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/40 p-4">
      <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b px-5 py-3">
          <h3 class="text-base font-semibold text-gray-800">Counter Close</h3>
          <button
            type="button"
            class="text-gray-500 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="cashCounterCloseBusy"
            @click="closeCounterCloseModal"
          >
            ✕
          </button>
        </div>

        <div class="px-5 py-4">
          <table class="w-full text-sm text-gray-700">
            <tr>
              <td class="py-1 font-semibold">User</td>
              <td class="py-1">{{ authInfo?.admin?.name || 'N/A' }}</td>
            </tr>
            <tr>
              <td class="py-1 font-semibold">Counter</td>
              <td class="py-1">{{ authInfo?.department?.name || 'N/A' }}</td>
            </tr>
          </table>

          <div class="mt-4">
            <label class="mb-1 block text-sm font-semibold text-gray-700">Closing Amount</label>
            <input
              v-model="counterCloseForm.closingAmount"
              type="number"
              step="0.01"
              min="0"
              :disabled="cashCounterCloseBusy"
              @keydown.enter.prevent="closeCounterFromBilling"
              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
              placeholder="যেমন: 5000"
            >
          </div>

          <div class="mt-3">
            <label class="mb-1 block text-sm font-semibold text-gray-700">Note (optional)</label>
            <textarea
              v-model="counterCloseForm.note"
              rows="2"
              :disabled="cashCounterCloseBusy"
              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
              placeholder="Counter close note"
            ></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 border-t px-5 py-3">
          <button
            type="button"
            class="rounded-md bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 disabled:opacity-60 disabled:cursor-not-allowed"
            :disabled="cashCounterCloseBusy"
            @click="closeCounterCloseModal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="rounded-md bg-rose-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60 disabled:cursor-not-allowed"
            :disabled="cashCounterCloseBusy"
            @click="closeCounterFromBilling"
          >
            {{ cashCounterCloseBusy ? 'Closing...' : 'Close & Print' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Dropdown highlight color - #4A90E2 (Light Accurate Blue) */
.dropdown-highlight-blue {
  background-color: #4A90E2 !important;
  color: white !important;
}

.dark .dropdown-highlight-blue {
  background-color: #4A90E2 !important;
  color: white !important;
}

.dropdown-hover-blue {
  background-color: #E8F0FE !important;
  color: #4A90E2 !important;
}

.dark .dropdown-hover-blue {
  background-color: #4A90E2 !important;
  color: white !important;
}

/* Default dropdown items for Gender: white background, black text */
.dropdown-default-white {
  background-color: #ffffff !important;
  color: #111827 !important;
}
.dropdown-default-white:hover {
  background-color: #f8fafc !important;
  color: #111827 !important;
}
.dark .dropdown-default-white {
  background-color: #1f2937 !important; /* slate-800 */
  color: #e5e7eb !important;
}
.dark .dropdown-default-white:hover {
  background-color: #374151 !important; /* slate-700 */
}

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

#receivingAmt::-webkit-outer-spin-button,
#receivingAmt::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

#receivingAmt {
  -moz-appearance: textfield;
  appearance: textfield;
}

/* Input focus improvements */
input:focus,
select:focus,
textarea:focus {
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}

/* Billing page text size adjustment */
.billing-page-text,
.billing-page-text * {
  font-size: 0.95rem !important;
}

.billing-page-text input,
.billing-page-text select,
.billing-page-text textarea,
.billing-page-text button {
  line-height: 1.35 !important;
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