<script setup>
import { ref, watch } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import BillingModal from '@/Components/BillingModal.vue';

const searchForm = useForm({
    case_id: '',
});

const showAddBillModal = ref(false);

const searchResults = ref(null);
const isLoading = ref(false);
const errorMessage = ref('');

let searchTimeout = null;

const billingTypes = [
    {
        id: 'appointment',
        name: 'Appointment',
        icon: '📅',
        route: 'backend.appoinment.create'
    },
    {
        id: 'opd',
        name: 'OPD',
        icon: '🩺',
        route: 'backend.opdpatient.create'
    },
    {
        id: 'pathology',
        name: 'Pathology',
        icon: '🧪',
        route: 'backend.pathology.create'
    },
    {
        id: 'radiology',
        name: 'Radiology',
        icon: '🔬',
        route: 'backend.radiology.create'
    },
    {
        id: 'blood_issue',
        name: 'Blood Issue',
        icon: '🩸',
        route: 'backend.bloodissue.create'
    },
    // {
    //     id: 'blood_component',
    //     name: 'Blood Component Issue',
    //     icon: '🩸',
    //     route: 'backend.bloodcomponentissue.create'
    // },
    {
        id: 'pharmacy',
        name: 'Pharmacy',
        icon: '💊',
        route: 'backend.pharmacybill.create'
    }
];

const navigateToBillingType = (type) => {
    router.visit(route(type.route));
};

let props = defineProps({
    filters: Object,
});

const filters = ref({
    case_id: props.filters?.case_id ?? '',
    numOfData: props.filters?.numOfData ?? 10,
});

const applyFilter = () => {
    router.get(route('backend.billing.search'), filters.value, { preserveState: true });
};

// Debounced search function for onChange
const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        if (filters.value.case_id.trim().length >= 2) {
            performSearch();
        } else if (filters.value.case_id.trim().length === 0) {
            searchResults.value = null;
            errorMessage.value = '';
        }
    }, 300); // 300ms delay
};

// Main search function
const performSearch = async () => {
    if (!filters.value.case_id.trim()) {
        errorMessage.value = 'Please enter a Case ID';
        searchResults.value = null;
        return;
    }

    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.get(route('backend.billing.search'), {
            params: { case_id: filters.value.case_id }
        });

        searchResults.value = response.data;
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Failed to search. Please try again.';
        searchResults.value = null;
    } finally {
        isLoading.value = false;
    }
};

// Search by button click
const searchByCase = async () => {
    await performSearch();
};

// Watch for input changes
watch(() => filters.value.case_id, () => {
    debouncedSearch();
});

// Modal functions
const openAddBillModal = () => {
    showAddBillModal.value = true;
};

const closeAddBillModal = () => {
    showAddBillModal.value = false;
};

const handleBillSave = (billData) => {
    // Handle the saved bill data here
    console.log('Bill saved:', billData);

    // You can process the data, send it to backend, etc.
    // For example:
    // router.post('/billing/save', billData);

    // Close the modal
    showAddBillModal.value = false;

    // Show success message or redirect as needed
    // You could also show a toast notification here
    alert('Bill saved successfully!');
};

const openAddBillButton = () => {
    router.visit(route('backend.billing.view'));
};

const openListBillButton = () => {
    router.visit(route('backend.billing.list'));
};

const selectBillingTypeFromModal = (type) => {
    closeAddBillModal();
    navigateToBillingType(type);
};

// Clear search results
const clearSearch = () => {
    filters.value.case_id = '';
    searchResults.value = null;
    errorMessage.value = '';
};
</script>

<template>
    <BackendLayout>
        <div class="w-full">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-2">

                <!-- Single Module Billing Section -->
                <div>
                    <div
                        class="bg-white border border-gray-200 rounded-md shadow-sm dark:bg-slate-900 dark:border-gray-700 h-full">
                        <div
                            class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center flex-wrap">
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Single Module Billing
                            </h2>
                            <div class="flex gap-2">
                                <button @click="openListBillButton"
                                    class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors duration-200 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                    </svg>
                                    Bill List
                                </button>
                                <button @click="openAddBillButton"
                                    class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors duration-200 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Bill
                                </button>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div v-for="type in billingTypes" :key="type.id" @click="navigateToBillingType(type)"
                                    class="group cursor-pointer border rounded-lg p-6 text-center transition-all duration-200 hover:shadow-md hover:border-blue-300 border-gray-200 dark:border-gray-600 bg-white dark:bg-slate-800">
                                    <div class="text-3xl mb-3">{{ type.icon }}</div>
                                    <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">{{ type.name }}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Case ID Search Section -->
                <div>
                    <div
                        class="bg-white border border-gray-200 rounded-md shadow-sm dark:bg-slate-900 dark:border-gray-700 h-full">
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">OPD/IPD Billing Through
                                Case Id</h2>
                        </div>

                        <div class="p-6">
                            <form @submit.prevent="searchByCase" class="space-y-4">
                                <div>
                                    <InputLabel for="case_id" value="Case ID" />
                                    <div class="flex mt-2">
                                        <div class="relative flex-1">
                                            <input id="case_id" type="text" placeholder="Enter Case ID "
                                                v-model="filters.case_id"
                                                class="block w-full px-3 py-2 pr-8 text-sm rounded-l-md border border-gray-300 dark:border-gray-600 dark:bg-slate-700 dark:text-slate-200 focus:border-blue-500 focus:outline-none"
                                                autocomplete="off" />

                                            <!-- Clear button -->
                                            <button v-if="filters.case_id" type="button" @click="clearSearch"
                                                class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                        <button type="submit"
                                            class="px-4 py-2 bg-blue-600 text-white text-sm rounded-r-md hover:bg-blue-700 transition-colors duration-200 flex items-center"
                                            :disabled="isLoading">
                                            <span v-if="isLoading">🔍 Searching...</span>
                                            <span v-else>🔍 Search</span>
                                        </button>
                                    </div>
                                    <InputError class="mt-2" :message="errorMessage" />

                                </div>
                            </form>

                            <!-- Search Results Section -->
                            <div v-if="isLoading" class="mt-4 text-center py-4">
                                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
                                <p class="mt-2 text-gray-600 dark:text-gray-300">Searching...</p>
                            </div>

                            <div v-else-if="searchResults" class="mt-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="text-md font-semibold text-gray-800 dark:text-gray-200">Search Results
                                    </h3>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ searchResults.length }} result(s) found
                                    </span>
                                </div>

                                <!-- Error message if no results found -->
                                <div v-if="searchResults.length === 0"
                                    class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4 dark:bg-yellow-900/20 dark:border-yellow-500">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd"
                                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-yellow-700 dark:text-yellow-300">
                                                No records found for Case ID: "{{ filters.case_id }}"
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Results table -->
                                <div v-else class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-slate-800">
                                            <tr>
                                                <th scope="col"
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                    Case ID</th>
                                                <th scope="col"
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                    Patient</th>
                                                <th scope="col"
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                    Payment Status</th>
                                                <!-- <th scope="col"
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                    Date</th>
                                                <th scope="col"
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                    Status</th> -->
                                                <th scope="col"
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                    Action</th>
                                            </tr>
                                        </thead>
                                        <tbody
                                            class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr v-for="result in searchResults" :key="result.id"
                                                class="hover:bg-gray-50 dark:hover:bg-slate-700">
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                                    {{ result.case_number }}
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                                    {{ result.patient_name }} <br>
                                                    <span class="text-xs text-gray-500">({{
                                                        result.patient_mobile }})</span>
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                                    {{ result.payment_status }} <br>
                                                </td>
                                                <!-- <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-200">
                                                    {{ result.created_at }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <span :class="{
                                                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': result.status === 'Active',
                                                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': result.status === 'Pending',
                                                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': result.status === 'Inactive'
                                                    }"
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                                        {{ result.status }}
                                                    </span>
                                                </td> -->
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">

                                                    <button
                                                        v-if="result.payment_status == 'Partial' || result.payment_status == 'Pending'"
                                                        @click="router.visit(route('backend.billing.edit', { id: result.id }))"
                                                        class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                        Edit
                                                    </button>
                                                    <a :href="route('backend.download.invoice', { id: result.id, module: 'billing' })"
                                                        target="_blank"
                                                        class="inline-block text-white bg-teal-500 rounded p-1 hover:text-black-900 dark:text-white dark:hover:text-white">
                                                        Invoice
                                                    </a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Billing Modal Component -->
        <BillingModal :show="showAddBillModal" @close="closeAddBillModal" @save="handleBillSave"
            @select-billing-type="selectBillingTypeFromModal" />
    </BackendLayout>
</template>

<style scoped>
/* Hover effects for billing type cards */
.group:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Focus states */
input:focus,
select:focus {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Button hover effects */
button:hover {
    transform: translateY(-1px);
    transition: all 0.2s ease;
}

/* Loading state for buttons */
button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Dark mode adjustments */
@media (prefers-color-scheme: dark) {
    .group:hover {
        box-shadow: 0 4px 12px rgba(255, 255, 255, 0.1);
    }
}

/* Smooth transitions for search results */
.mt-6 {
    animation: fadeIn 0.3s ease-in-out;
}

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
</style>