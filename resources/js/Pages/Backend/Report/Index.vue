<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    filters: Object,
    hasData: Boolean,
    reportData: Object,
});

const loading = ref(false);
const downloadingPdf = ref(false);
const hasFiltered = ref(false);
const showDatePicker = ref(false);
const currentMonth = ref(new Date().getMonth());
const currentYear = ref(new Date().getFullYear());
const selectingFrom = ref(true);
const selectionMode = ref('single');

const filters = ref({
    dateFrom: props.filters?.dateFrom ?? '',
    dateTo: props.filters?.dateTo ?? '',
    module: props.filters?.module ?? 'all_module',
    singleDate: props.filters?.singleDate ?? '',
});

const modules = [
    { id: 'all_module', name: 'All Module' },
    { id: 'billing', name: 'Billing' },
    { id: 'pharmacy', name: 'Pharmacy' },
    { id: 'opd', name: 'OPD' },
    { id: 'ipd', name: 'IPD' },
];

const getModuleDisplayName = (module) => {
    if (!module) return 'N/A';

    const normalized = String(module).toLowerCase();

    if (normalized === 'opd' || normalized === 'ipd') {
        return normalized.toUpperCase();
    }

    if (normalized === 'medicine') {
        return 'Pharmacy';
    }

    return normalized.charAt(0).toUpperCase() + normalized.slice(1);
};

const formatMoney = (amount) => {
    const value = Number(amount || 0);
    return `৳${value.toLocaleString('en-BD', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
};

const moduleSummary = computed(() => {
    const rows = props.reportData?.data || [];
    const summary = rows.reduce((acc, row) => {
        const moduleKey = String(row?.module || 'unknown').toLowerCase();
        if (!acc[moduleKey]) {
            acc[moduleKey] = {
                module: moduleKey,
                label: getModuleDisplayName(moduleKey),
                records: 0,
                revenue: 0,
            };
        }

        acc[moduleKey].records += Number(row?.records || 0);
        acc[moduleKey].revenue += Number(row?.revenue || 0);
        return acc;
    }, {});

    return Object.values(summary).sort((a, b) => b.revenue - a.revenue);
});

const getReportRowDetails = (item) => {
    const moduleKey = String(item?.module || '').toLowerCase();

    if (moduleKey === 'opd') {
        const patient = item?.patient_name || 'N/A';
        const doctor = item?.doctor_name || 'N/A';
        return `${patient} | Dr. ${doctor}`;
    }

    if (moduleKey === 'ipd') {
        const patient = item?.patient_name || 'N/A';
        const bed = item?.bed_number || 'N/A';
        return `${patient} | Bed: ${bed}`;
    }

    if (moduleKey === 'billing') {
        return `Bill No: ${item?.bill_no || 'N/A'}`;
    }

    if (moduleKey === 'pharmacy' || moduleKey === 'medicine') {
        const itemName = item?.item_name || 'N/A';
        const qty = item?.quantity ?? 'N/A';
        return `${itemName} | Qty: ${qty}`;
    }

    return '-';
};

const dateRangeDisplay = computed(() => {
    if (selectionMode.value === 'single' && filters.value.singleDate) {
        const date = createDateFromString(filters.value.singleDate);
        return date.toLocaleDateString('en-GB'); 
    } else if (selectionMode.value === 'range') {
        if (filters.value.dateFrom && filters.value.dateTo) {
            const fromDate = createDateFromString(filters.value.dateFrom);
            const toDate = createDateFromString(filters.value.dateTo);
            return `${fromDate.toLocaleDateString('en-GB')} - ${toDate.toLocaleDateString('en-GB')}`;
        } else if (filters.value.dateFrom) {
            const fromDate = createDateFromString(filters.value.dateFrom);
            return `From ${fromDate.toLocaleDateString('en-GB')}`;
        } else if (filters.value.dateTo) {
            const toDate = createDateFromString(filters.value.dateTo);
            return `Until ${toDate.toLocaleDateString('en-GB')}`;
        }
    }
    return 'Select date';
});

const formatDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const createDateFromString = (dateString) => {
    if (!dateString) return null;
    const [year, month, day] = dateString.split('-').map(Number);
    return new Date(year, month - 1, day, 12, 0, 0);
};

const createLocalDate = (year, month, day) => {
    return new Date(year, month, day, 12, 0, 0);
};

const applyFilter = () => {
    loading.value = true;
    hasFiltered.value = true;

    let filterData = { ...filters.value };

    if (selectionMode.value === 'single') {
        filterData.dateFrom = '';
        filterData.dateTo = '';
    } else {
        filterData.singleDate = '';
    }

    router.get(route('backend.report.index'), filterData, {
        preserveState: true,
        onFinish: () => {
            loading.value = false;
        }
    });
};

const downloadPdf = async () => {
    downloadingPdf.value = true;
    
    let filterData = { ...filters.value };

    if (selectionMode.value === 'single') {
        filterData.dateFrom = '';
        filterData.dateTo = '';
    } else {
        filterData.singleDate = '';
    }

    try {
        const params = new URLSearchParams();
        Object.keys(filterData).forEach(key => {
            if (filterData[key]) {
                params.append(key, filterData[key]);
            }
        });

        let url = `${route('backend.report.generate-pdf')}?${params.toString()}`;
        if (params.toString().length > 0) {
            url += '&inline=1';
        } else {
            url += 'inline=1';
        }

        window.open(url, '_blank');

    } catch (error) {
        console.error('Download error:', error);
        const form = document.createElement('form');
        form.method = 'GET';
        form.action = route('backend.report.generate-pdf');
        form.target = '_blank';
        
        Object.keys(filterData).forEach(key => {
            if (filterData[key]) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = filterData[key];
                form.appendChild(input);
            }
        });

        // Request inline viewing in new tab
        const inlineInput = document.createElement('input');
        inlineInput.type = 'hidden';
        inlineInput.name = 'inline';
        inlineInput.value = '1';
        form.appendChild(inlineInput);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
    
    setTimeout(() => {
        downloadingPdf.value = false;
    }, 2000);
};

const resetFilters = () => {
    filters.value = {
        dateFrom: '',
        dateTo: '',
        module: 'all_module',
        singleDate: ''
    };
    hasFiltered.value = false;
    selectionMode.value = 'single';
    selectingFrom.value = true;

    router.get(route('backend.report.index'), {
        dateFrom: '',
        dateTo: '',
        module: 'all_module',
        singleDate: ''
    }, {
        preserveState: true
    });
};

const monthNames = ["January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"];

const daysInMonth = (month, year) => {
    return new Date(year, month + 1, 0).getDate();
};

const firstDayOfMonth = (month, year) => {
    return new Date(year, month, 1).getDay();
};

const getMonthDays = (month, year) => {
    const days = [];
    const daysInCurrentMonth = daysInMonth(month, year);
    const firstDay = firstDayOfMonth(month, year);

    const prevMonth = month === 0 ? 11 : month - 1;
    const prevYear = month === 0 ? year - 1 : year;
    const daysInPrevMonth = daysInMonth(prevMonth, prevYear);

    for (let i = firstDay - 1; i >= 0; i--) {
        days.push({
            day: daysInPrevMonth - i,
            isCurrentMonth: false,
            isOtherMonth: true,
            date: createLocalDate(prevYear, prevMonth, daysInPrevMonth - i)
        });
    }

    for (let day = 1; day <= daysInCurrentMonth; day++) {
        days.push({
            day: day,
            isCurrentMonth: true,
            isOtherMonth: false,
            date: createLocalDate(year, month, day)
        });
    }

    const totalCells = 42;
    const remainingCells = totalCells - days.length;
    const nextMonth = month === 11 ? 0 : month + 1;
    const nextYear = month === 11 ? year + 1 : year;

    for (let day = 1; day <= remainingCells; day++) {
        days.push({
            day: day,
            isCurrentMonth: false,
            isOtherMonth: true,
            date: createLocalDate(nextYear, nextMonth, day)
        });
    }

    return days;
};

const getDaysArray = computed(() => {
    return getMonthDays(currentMonth.value, currentYear.value);
});

const getNextMonthDays = computed(() => {
    const nextMonth = currentMonth.value === 11 ? 0 : currentMonth.value + 1;
    const nextYear = currentMonth.value === 11 ? currentYear.value + 1 : currentYear.value;
    return getMonthDays(nextMonth, nextYear);
});

const isDateInRange = (date) => {
    if (selectionMode.value === 'single') {
        return formatDate(date) === filters.value.singleDate;
    }
    if (!filters.value.dateFrom || !filters.value.dateTo) return false;
    const dateStr = formatDate(date);
    return dateStr >= filters.value.dateFrom && dateStr <= filters.value.dateTo;
};

const isDateSelected = (date) => {
    const dateStr = formatDate(date);
    if (selectionMode.value === 'single') {
        return dateStr === filters.value.singleDate;
    }
    return dateStr === filters.value.dateFrom || dateStr === filters.value.dateTo;
};

const isDateStart = (date) => {
    if (selectionMode.value === 'single') return false;
    const dateStr = formatDate(date);
    return dateStr === filters.value.dateFrom;
};

const isDateEnd = (date) => {
    if (selectionMode.value === 'single') return false;
    const dateStr = formatDate(date);
    return dateStr === filters.value.dateTo;
};

const selectDate = (date) => {
    const dateStr = formatDate(date);

    if (selectionMode.value === 'single') {
        filters.value.singleDate = dateStr;
        filters.value.dateFrom = '';
        filters.value.dateTo = '';
    } else {
        if (selectingFrom.value || !filters.value.dateFrom) {
            filters.value.dateFrom = dateStr;
            filters.value.dateTo = '';
            filters.value.singleDate = '';
            selectingFrom.value = false;
        } else {
            if (dateStr < filters.value.dateFrom) {
                filters.value.dateTo = filters.value.dateFrom;
                filters.value.dateFrom = dateStr;
            } else {
                filters.value.dateTo = dateStr;
            }
            selectingFrom.value = true;
        }
    }
};

const switchToSingleMode = () => {
    selectionMode.value = 'single';
    filters.value.dateFrom = '';
    filters.value.dateTo = '';
    selectingFrom.value = true;
};

const switchToRangeMode = () => {
    selectionMode.value = 'range';
    filters.value.singleDate = '';
    selectingFrom.value = true;
};

const prevMonth = () => {
    if (currentMonth.value === 0) {
        currentMonth.value = 11;
        currentYear.value--;
    } else {
        currentMonth.value--;
    }
};

const nextMonth = () => {
    if (currentMonth.value === 11) {
        currentMonth.value = 0;
        currentYear.value++;
    } else {
        currentMonth.value++;
    }
};

const toggleDatePicker = () => {
    showDatePicker.value = !showDatePicker.value;
};

const closeDatePicker = () => {
    showDatePicker.value = false;
};

const clearDates = () => {
    filters.value.dateFrom = '';
    filters.value.dateTo = '';
    filters.value.singleDate = '';
    selectingFrom.value = true;
};

const handleClickOutside = (event) => {
    if (!event.target.closest('.date-picker-container')) {
        showDatePicker.value = false;
    }
};

const formatDateForDisplay = (dateString) => {
    if (!dateString) return '';
    const date = createDateFromString(dateString);
    return date ? date.toLocaleDateString('en-GB') : '';
};

onMounted(() => {
    if (props.filters && Object.keys(props.filters).some(key => props.filters[key])) {
        hasFiltered.value = true;

        if (props.filters.singleDate) {
            selectionMode.value = 'single';
        } else if (props.filters.dateFrom || props.filters.dateTo) {
            selectionMode.value = 'range';
        }
    }

    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <BackendLayout>
        <div class="report-generator">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">Report Generator</h2>
                    <p class="text-gray-600">Generate reports by selecting date range and module</p>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <div class="flex flex-wrap items-end gap-4 mb-4">
                        <div class="flex-1 min-w-[250px] relative date-picker-container">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ selectionMode === 'single' ? 'Date' : 'Date Range' }}
                            </label>
                            <div class="relative">
                                <input type="text" readonly :value="dateRangeDisplay" @click="toggleDatePicker"
                                    class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent cursor-pointer bg-white"
                                    :placeholder="selectionMode === 'single' ? 'Select date' : 'Select date range'">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                        </path>
                                    </svg>
                                </div>
                            </div>

                            <!-- Date Picker -->
                            <div v-if="showDatePicker"
                                class="absolute z-10 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl min-w-[680px] max-w-[800px]">
                                <div class="flex border-b border-gray-200 bg-gray-50 rounded-t-lg">
                                    <button @click="switchToSingleMode" :class="[
                                        'flex-1 px-4 py-3 text-sm font-medium transition-colors relative',
                                        selectionMode === 'single'
                                            ? 'bg-white text-blue-600 border-b-2 border-blue-600 shadow-sm'
                                            : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                                    ]">
                                        <span class="flex items-center justify-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                                </path>
                                            </svg>
                                            Single Date
                                        </span>
                                    </button>
                                    <button @click="switchToRangeMode" :class="[
                                        'flex-1 px-4 py-3 text-sm font-medium transition-colors relative',
                                        selectionMode === 'range'
                                            ? 'bg-white text-blue-600 border-b-2 border-blue-600 shadow-sm'
                                            : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                                    ]">
                                        <span class="flex items-center justify-center gap-2">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                                </path>
                                            </svg>
                                            Date Range
                                        </span>
                                    </button>
                                </div>

                                <div class="flex">
                                    <div class="flex-1 p-4">
                                        <div class="flex items-center justify-between mb-4">
                                            <button @click="prevMonth"
                                                class="p-2 hover:bg-gray-100 rounded-full transition-colors">
                                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                                </svg>
                                            </button>
                                            <h3 class="text-lg font-semibold text-gray-900">
                                                {{ monthNames[currentMonth] }} {{ currentYear }}
                                            </h3>
                                            <div class="w-9"></div>
                                        </div>

                                        <div class="grid grid-cols-7 gap-1 mb-2">
                                            <div class="text-center text-xs font-medium text-gray-500 py-2">Su</div>
                                            <div class="text-center text-xs font-medium text-gray-500 py-2">Mo</div>
                                            <div class="text-center text-xs font-medium text-gray-500 py-2">Tu</div>
                                            <div class="text-center text-xs font-medium text-gray-500 py-2">We</div>
                                            <div class="text-center text-xs font-medium text-gray-500 py-2">Th</div>
                                            <div class="text-center text-xs font-medium text-gray-500 py-2">Fr</div>
                                            <div class="text-center text-xs font-medium text-gray-500 py-2">Sa</div>
                                        </div>

                                        <div class="grid grid-cols-7 gap-1">
                                            <button v-for="dayObj in getDaysArray" :key="`${dayObj.date.getTime()}`"
                                                @click="selectDate(dayObj.date)" :class="[
                                                    'w-9 h-9 text-sm flex items-center justify-center transition-all duration-200',
                                                    {
                                                        'text-gray-400': dayObj.isOtherMonth,
                                                        'text-gray-900': dayObj.isCurrentMonth && !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date),
                                                        'bg-blue-600 text-white font-medium': isDateSelected(dayObj.date),
                                                        'bg-blue-100 text-blue-600': isDateInRange(dayObj.date) && !isDateSelected(dayObj.date) && selectionMode === 'range',
                                                        'hover:bg-gray-100': !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date) && dayObj.isCurrentMonth,
                                                        'hover:bg-gray-50': !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date) && dayObj.isOtherMonth,
                                                        'rounded-l-full': isDateStart(dayObj.date) && filters.dateFrom !== filters.dateTo,
                                                        'rounded-r-full': isDateEnd(dayObj.date) && filters.dateFrom !== filters.dateTo,
                                                        'rounded-full': isDateSelected(dayObj.date) || (isDateInRange(dayObj.date) && selectionMode === 'single'),
                                                        'cursor-pointer': dayObj.isCurrentMonth
                                                    }
                                                ]">
                                                {{ dayObj.day }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="w-px bg-gray-200"></div>

                                    <div class="flex-1 p-4">
                                        <div class="flex items-center justify-between mb-4">
                                            <div class="w-9"></div>
                                            <h3 class="text-lg font-semibold text-gray-900">
                                                {{ monthNames[currentMonth === 11 ? 0 : currentMonth + 1] }}
                                                {{ currentMonth === 11 ? currentYear + 1 : currentYear }}
                                            </h3>
                                            <button @click="nextMonth"
                                                class="p-2 hover:bg-gray-100 rounded-full transition-colors">
                                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M9 5l7 7-7 7"></path>
                                                </svg>
                                            </button>
                                        </div>

                                        <div class="grid grid-cols-7 gap-1 mb-2">
                                            <div class="text-center text-xs font-medium text-gray-500 py-2">Su</div>
                                            <div class="text-center text-xs font-medium text-gray-500 py-2">Mo</div>
                                            <div class="text-center text-xs font-medium text-gray-500 py-2">Tu</div>
                                            <div class="text-center text-xs font-medium text-gray-500 py-2">We</div>
                                            <div class="text-center text-xs font-medium text-gray-500 py-2">Th</div>
                                            <div class="text-center text-xs font-medium text-gray-500 py-2">Fr</div>
                                            <div class="text-center text-xs font-medium text-gray-500 py-2">Sa</div>
                                        </div>

                                        <div class="grid grid-cols-7 gap-1">
                                            <button v-for="dayObj in getNextMonthDays" :key="`${dayObj.date.getTime()}`"
                                                @click="selectDate(dayObj.date)" :class="[
                                                    'w-9 h-9 text-sm flex items-center justify-center transition-all duration-200',
                                                    {
                                                        'text-gray-400': dayObj.isOtherMonth,
                                                        'text-gray-900': dayObj.isCurrentMonth && !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date),
                                                        'bg-blue-600 text-white font-medium': isDateSelected(dayObj.date),
                                                        'bg-blue-100 text-blue-600': isDateInRange(dayObj.date) && !isDateSelected(dayObj.date) && selectionMode === 'range',
                                                        'hover:bg-gray-100': !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date) && dayObj.isCurrentMonth,
                                                        'hover:bg-gray-50': !isDateSelected(dayObj.date) && !isDateInRange(dayObj.date) && dayObj.isOtherMonth,
                                                        'rounded-l-full': isDateStart(dayObj.date) && filters.dateFrom !== filters.dateTo,
                                                        'rounded-r-full': isDateEnd(dayObj.date) && filters.dateFrom !== filters.dateTo,
                                                        'rounded-full': isDateSelected(dayObj.date) || (isDateInRange(dayObj.date) && selectionMode === 'single'),
                                                        'cursor-pointer': dayObj.isCurrentMonth
                                                    }
                                                ]">
                                                {{ dayObj.day }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-between items-center p-4 border-t border-gray-200 bg-gray-50">
                                    <div class="flex gap-2">
                                        <button @click="clearDates"
                                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-md transition-colors">
                                            Clear
                                        </button>
                                    </div>
                                    <div class="flex gap-2">
                                        <button @click="closeDatePicker"
                                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-md hover:bg-gray-100 transition-colors">
                                            Cancel
                                        </button>
                                        <button @click="closeDatePicker"
                                            class="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                            Apply
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Module</label>
                            <select v-model="filters.module"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option v-for="module in modules" :key="module.id" :value="module.id">
                                    {{ module.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex gap-2">
                            <button @click="applyFilter" :disabled="loading"
                                class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition-colors">
                                <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none"
                                    viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                {{ loading ? 'Generating...' : 'Generate Report' }}
                            </button>
                            
                            <button v-if="hasFiltered" @click="resetFilters"
                                class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                                Reset
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Report Results -->
                <div v-if="hasData && props.reportData" class="bg-white rounded-lg border border-gray-200">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-lg font-semibold text-gray-800">Report Results</h3>
                        <button @click="downloadPdf" :disabled="downloadingPdf"
                            class="px-4 py-2 bg-green-600 text-white text-sm rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition-colors">
                            <svg v-if="downloadingPdf" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            {{ downloadingPdf ? 'Generating PDF...' : 'Download PDF' }}
                        </button>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                            <div class="bg-blue-50 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-blue-600">Total Records</p>
                                        <p class="text-2xl font-bold text-blue-900">{{ props.reportData.total || 0 }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-green-50 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-green-600">Revenue</p>
                                        <p class="text-2xl font-bold text-green-900">{{ formatMoney(props.reportData.revenue || 0) }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-yellow-50 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-yellow-600">Average</p>
                                        <p class="text-2xl font-bold text-yellow-900">{{ formatMoney(props.reportData.average || 0) }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- <div class="bg-purple-50 rounded-lg p-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-purple-600">Growth</p>
                                        <p class="text-2xl font-bold text-purple-900">{{ (props.reportData.growth || 0) }}%</p>
                                    </div>
                                </div>
                            </div> -->
                        </div>

                        <div v-if="moduleSummary.length" class="mb-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                            <div
                                v-for="item in moduleSummary"
                                :key="item.module"
                                class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold text-gray-700">{{ item.label }}</span>
                                    <span class="text-xs text-gray-500">{{ item.records }} Records</span>
                                </div>
                                <div class="mt-1 text-sm font-bold text-gray-900">{{ formatMoney(item.revenue) }}</div>
                            </div>
                        </div>

                        <!-- Data Table -->
                        <div v-if="props.reportData.data && props.reportData.data.length > 0" class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Date
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Module
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Records
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Revenue
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Details
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(item, index) in props.reportData.data" :key="index" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ new Date(item.date).toLocaleDateString() }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                                :class="{
                                                    'bg-yellow-100 text-yellow-800': item.module === 'pharmacy' || item.module === 'medicine',
                                                    'bg-purple-100 text-purple-800': item.module === 'opd',
                                                    'bg-pink-100 text-pink-800': item.module === 'ipd',
                                                    'bg-gray-100 text-gray-800': !item.module
                                                }">
                                                {{ getModuleDisplayName(item.module) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ item.records || 0 }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatMoney(item.revenue || 0) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                                :class="{
                                                    'bg-green-100 text-green-800': item.status === 'completed',
                                                    'bg-yellow-100 text-yellow-800': item.status === 'pending',
                                                    'bg-red-100 text-red-800': item.status === 'failed',
                                                    'bg-gray-100 text-gray-800': !item.status
                                                }">
                                                {{ item.status ? item.status.charAt(0).toUpperCase() + item.status.slice(1) : 'N/A' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700">
                                            {{ getReportRowDetails(item) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- No Data Message -->
                        <div v-else class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No data available</h3>
                            <p class="mt-1 text-sm text-gray-500">No records found for the selected criteria.</p>
                        </div>
                    </div>
                </div>

                <!-- No Results Message -->
                <div v-else-if="hasFiltered && !hasData" class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No results found</h3>
                    <p class="mt-1 text-sm text-gray-500">Try adjusting your filters to see more results.</p>
                    <button @click="resetFilters" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200">
                        Clear filters
                    </button>
                </div>

                <!-- Initial State -->
                <div v-else class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Generate a report</h3>
                    <p class="mt-1 text-sm text-gray-500">Select your filters and click "Generate Report" to get started.</p>
                </div>
            </div>
        </div>
    </BackendLayout>
</template>

<style scoped>
.report-generator {
    min-height: 100vh;
}

/* Custom scrollbar for table */
.overflow-x-auto::-webkit-scrollbar {
    height: 10px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: color-mix(in srgb, var(--app-theme-soft) 26%, #e2e8f0);
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: color-mix(in srgb, var(--app-theme-primary) 40%, #94a3b8);
    border-radius: 8px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: color-mix(in srgb, var(--app-theme-primary) 56%, #64748b);
}

/* Animation for loading states */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.bg-white {
    animation: fadeIn 0.3s ease-in-out;
}

/* Date picker transitions */
.date-picker-container .absolute {
    animation: fadeIn 0.2s ease-in-out;
}
</style>