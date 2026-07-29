<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps(['expense', 'expenseHeads', 'id']);
const expenseHeadSearch = ref('');
const showExpenseHeadSuggestions = ref(false);
const highlightedExpenseHeadIndex = ref(0);

const sortedExpenseHeads = computed(() => {
    return [...(props.expenseHeads || [])].sort((a, b) => {
        const nameA = (a.name || '').toLowerCase();
        const nameB = (b.name || '').toLowerCase();
        return nameA.localeCompare(nameB, 'en', { numeric: true });
    });
});

const filteredExpenseHeads = computed(() => {
    const query = expenseHeadSearch.value.trim().toLowerCase();
    if (!query) {
        return sortedExpenseHeads.value;
    }

    return sortedExpenseHeads.value.filter((head) => head.name?.toLowerCase().includes(query));
});

const form = useForm({
    expense_header_id: props.expense?.expense_header_id ?? '',
    bill_number: props.expense?.bill_number ?? '',
    case_id: props.expense?.case_id ?? '',
    name: props.expense?.name ?? '',
    document: null,
    documentPreview: props.expense?.document ?? null,
    description: props.expense?.description ?? '',
    amount: props.expense?.amount ?? '',
    date: props.expense?.date ? new Date(props.expense.date).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
    _method: props.expense?.id ? 'put' : 'post',
});

const handleDocumentChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.document = file;

        // Create preview URL
        const url = URL.createObjectURL(file);
        form.documentPreview = url;
    }
};

const removeDocument = () => {
    form.document = null;
    form.documentPreview = null;
    // Reset file input
    document.getElementById('document').value = '';
};

const syncExpenseHeadSearch = () => {
    const selectedHead = (props.expenseHeads || []).find((head) => String(head.id) === String(form.expense_header_id));
    expenseHeadSearch.value = selectedHead?.name || '';
};

const selectExpenseHead = (head) => {
    if (!head) return;

    form.expense_header_id = head.id;
    expenseHeadSearch.value = head.name;
    highlightedExpenseHeadIndex.value = 0;
    showExpenseHeadSuggestions.value = false;
};

const handleExpenseHeadInput = (event) => {
    const value = event.target.value;
    expenseHeadSearch.value = value;
    showExpenseHeadSuggestions.value = true;

    const matchedHead = sortedExpenseHeads.value.find((head) => head.name?.toLowerCase() === value.trim().toLowerCase());
    if (matchedHead) {
        form.expense_header_id = matchedHead.id;
        highlightedExpenseHeadIndex.value = filteredExpenseHeads.value.findIndex((head) => head.id === matchedHead.id);
    } else {
        form.expense_header_id = '';
        highlightedExpenseHeadIndex.value = 0;
    }
};

const handleExpenseHeadKeydown = (event) => {
    if (event.key === 'Escape') {
        showExpenseHeadSuggestions.value = false;
        return;
    }

    if (!showExpenseHeadSuggestions.value || !filteredExpenseHeads.value.length) {
        if (event.key === 'Enter' && expenseHeadSearch.value.trim()) {
            const matchedHead = sortedExpenseHeads.value.find((head) => head.name?.toLowerCase() === expenseHeadSearch.value.trim().toLowerCase());
            if (matchedHead) {
                selectExpenseHead(matchedHead);
            }
        }
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlightedExpenseHeadIndex.value = Math.min(highlightedExpenseHeadIndex.value + 1, filteredExpenseHeads.value.length - 1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlightedExpenseHeadIndex.value = Math.max(highlightedExpenseHeadIndex.value - 1, 0);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const selectedHead = filteredExpenseHeads.value[highlightedExpenseHeadIndex.value];
        if (selectedHead) {
            selectExpenseHead(selectedHead);
        }
    }
};

const focusExpenseHeadInput = () => {
    showExpenseHeadSuggestions.value = true;
};

const hideExpenseHeadSuggestions = () => {
    setTimeout(() => {
        showExpenseHeadSuggestions.value = false;
    }, 120);
};

const submit = () => {
    const routeName = props.id ? route('backend.expense.update', props.id) : route('backend.expense.store');

    form.transform(data => ({
        ...data,
        remember: '',
        isDirty: false,
    })).post(routeName, {
        onSuccess: (response) => {
            if (!props.id) {
                form.reset();
                // Reset date to today
                form.date = new Date().toISOString().split('T')[0];
            }
            displayResponse(response);
        },
        onError: (errorObject) => {
            displayWarning(errorObject);
        },
    });
};

// Format amount input
const formatAmount = (event) => {
    let value = event.target.value.replace(/[^\d.]/g, '');
    if (value) {
        const parts = value.split('.');
        if (parts.length > 2) {
            value = parts[0] + '.' + parts[1];
        }
        if (parts[1] && parts[1].length > 2) {
            value = parts[0] + '.' + parts[1].substring(0, 2);
        }
    }
    form.amount = value;
};

watch(() => form.expense_header_id, () => {
    syncExpenseHeadSearch();
}, { immediate: true });

onMounted(() => {
    // Set today's date as default if creating new expense
    if (!props.expense?.id) {
        form.date = new Date().toISOString().split('T')[0];
    }

    syncExpenseHeadSearch();
});

const goToExpenseList = () => {
    router.visit(route('backend.expense.index'));
};

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit(route('backend.dashboard'));
    }
};

</script>

<template>
    <BackendLayout>
        <div
            class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md">

            <div
                class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-2 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <button @click="goBack"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-red-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-red-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-red-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M10.5 19.5 3 12m7.5-7.5L3 12m7.5 0H21"></path>
                            </svg>
                            Back
                        </button>

                        <button @click="goToExpenseList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Expense List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">

                <!-- First Row: Expense Head, Name, Invoice Number, Date -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4 mb-4">
                    <!-- Expense Head -->
                    <div class="col-span-1 relative">
                        <InputLabel for="expense_header_id" value="Expense Head *" />
                        <input id="expense_header_id" v-model="expenseHeadSearch" type="text"
                            placeholder="Type to search expense head"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            @input="handleExpenseHeadInput"
                            @focus="focusExpenseHeadInput"
                            @keydown="handleExpenseHeadKeydown"
                            @blur="hideExpenseHeadSuggestions" />

                        <ul v-if="showExpenseHeadSuggestions && filteredExpenseHeads.length"
                            class="absolute z-20 mt-1 w-full max-h-48 overflow-y-auto rounded-md border border-slate-200 bg-white shadow-lg dark:border-slate-600 dark:bg-slate-800">
                            <li v-for="(head, index) in filteredExpenseHeads" :key="head.id"
                                @mousedown.prevent="selectExpenseHead(head)"
                                :class="[
                                    'cursor-pointer px-3 py-2 text-sm',
                                    highlightedExpenseHeadIndex === index
                                        ? 'bg-indigo-100 text-indigo-700 dark:bg-slate-700 dark:text-indigo-200'
                                        : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700'
                                ]">
                                {{ head.name }}
                            </li>
                        </ul>

                        <InputError class="mt-2" :message="form.errors.expense_header_id" />
                    </div>

                    <!-- Name -->
                    <div class="col-span-1">
                        <InputLabel for="name" value="Name *" />
                        <input id="name" v-model="form.name" type="text" placeholder="Expense Name"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <!-- Invoice Number -->
                    <div class="col-span-1">
                        <InputLabel for="bill_number" value="Bill Number" />
                        <input id="bill_number" v-model="form.bill_number" type="text"
                            placeholder="Bill Number"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                        <InputError class="mt-2" :message="form.errors.bill_number" />
                    </div>

                    <!-- Date -->
                    <div class="col-span-1">
                        <InputLabel for="date" value="Date *" />
                        <input id="date" v-model="form.date" type="date" :max="new Date().toISOString().split('T')[0]"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300
                        dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200
                        focus:border-indigo-300 dark:focus:border-slate-600" />
                        <InputError class="mt-2" :message="form.errors.date" />
                    </div>
                </div>

                <!-- Second Row: Amount and Document -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-4">
                    <!-- Amount -->
                    <div class="col-span-1">
                        <InputLabel for="amount" value="Amount (TK.) *" />
                        <input id="amount" v-model="form.amount" @input="formatAmount" type="text" placeholder="0.00"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600" />
                        <InputError class="mt-2" :message="form.errors.amount" />
                    </div>

                    <!-- Document Upload -->
                    <div class="col-span-1">
                        <InputLabel for="document" value="Attach Document" />

                        <!-- Document preview -->
                        <div v-if="form.documentPreview" class="mb-2">
                            <div class="flex items-center justify-between p-2 bg-gray-100 dark:bg-gray-800 rounded">
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ form.document?.name || 'Current Document' }}
                                </span>
                                <div class="flex gap-2">
                                    <a v-if="!form.document && form.documentPreview" :href="form.documentPreview"
                                        target="_blank" class="text-blue-600 hover:underline text-sm">View</a>
                                    <button type="button" @click="removeDocument"
                                        class="text-red-600 hover:underline text-sm">Remove</button>
                                </div>
                            </div>
                        </div>

                        <div class="relative">
                            <input id="document" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                @change="handleDocumentChange" />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12">
                                    </path>
                                </svg>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Supported formats: PDF, DOC, DOCX, JPG, JPEG, PNG (Max: 10MB)
                        </p>
                        <InputError class="mt-2" :message="form.errors.document" />
                    </div>
                </div>

                <!-- Third Row: Description (full width) -->
                <div class="grid grid-cols-1 mb-4">
                    <div class="col-span-1">
                        <InputLabel for="description" value="Description" />
                        <textarea id="description" v-model="form.description" rows="4"
                            placeholder="Enter description..."
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"></textarea>
                        <InputError class="mt-2" :message="form.errors.description" />
                    </div>
                </div>

                <div class="flex items-center justify-end mt-6">
                    <PrimaryButton type="submit" class="ms-4" :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing">
                        {{ form.processing ? 'Processing...' : ((props.id ?? false) ? 'Update Expense' : 'Save Expense')
                        }}
                    </PrimaryButton>
                </div>
            </form>

        </div>
    </BackendLayout>
</template>

<style scoped>
.grid {
    gap: 1rem;
}

input[type="file"]::-webkit-file-upload-button {
    visibility: hidden;
}

input[type="file"]::before {
    content: 'Drop a file here or click';
    display: inline-block;
    background: linear-gradient(top, #f9f9f9, #e3e3e3);
    border: 1px solid #999;
    border-radius: 3px;
    padding: 5px 8px;
    outline: none;
    white-space: nowrap;
    -webkit-user-select: none;
    cursor: pointer;
    text-shadow: 1px 1px #fff;
    font-weight: 700;
    font-size: 10pt;
}

input[type="file"]:hover::before {
    border-color: black;
}

input[type="file"]:active::before {
    background: -webkit-linear-gradient(top, #e3e3e3, #f9f9f9);
}
</style>