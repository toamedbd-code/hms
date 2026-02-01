<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close', 'created']);

const form = useForm({
    name: '',
    phone: '',
    contact_person_name: '',
    contact_person_phone: '',
    drug_lisence_no: '',
    address: ''
});

const closeModal = () => {
    form.reset();
    form.clearErrors();
    emit('close');
};

const submitForm = () => {
    form.post(route('backend.medicinesupplier.store'), {
        onSuccess: (response) => {
            const createdSupplier = response.props?.flash?.data || {
                id: Date.now(),
                name: form.name
            };

            emit('created', createdSupplier, response);
            closeModal();
        },
        onError: (errors) => {
            console.log('Supplier creation errors:', errors);
        }
    });
};

watch(() => props.show, (newVal) => {
    if (!newVal) {
        form.reset();
        form.clearErrors();
    }
});
</script>


<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog"
        aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true"
                @click="closeModal"></div>

            <!-- This element is to trick the browser into centering the modal contents. -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal panel -->
            <div
                class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium leading-6 text-gray-900" id="modal-title">
                            Add New Supplier
                        </h3>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitForm">
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="supplier_name" class="block text-sm font-medium text-gray-700">
                                    Supplier Name *
                                </label>
                                <input id="supplier_name" v-model="form.name" type="text" required
                                    class="block w-full mt-1 p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    placeholder="Enter supplier name" />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                            </div>

                            <div>
                                <label for="supplier_phone" class="block text-sm font-medium text-gray-700">
                                    Phone
                                </label>
                                <input id="supplier_phone" v-model="form.phone" type="text"
                                    class="block w-full mt-1 p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    placeholder="Enter phone number" />
                                <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}
                                </p>
                            </div>

                            <div>
                                <label for="contact_person_name" class="block text-sm font-medium text-gray-700">
                                    Contact Person Name
                                </label>
                                <input id="contact_person_name" v-model="form.contact_person_name" type="text"
                                    class="block w-full mt-1 p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    placeholder="Enter contact person name" />
                                <p v-if="form.errors.contact_person_name" class="mt-1 text-sm text-red-600">{{
                                    form.errors.contact_person_name }}</p>
                            </div>

                            <div>
                                <label for="contact_person_phone" class="block text-sm font-medium text-gray-700">
                                    Contact Person Phone
                                </label>
                                <input id="contact_person_phone" v-model="form.contact_person_phone" type="text"
                                    class="block w-full mt-1 p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    placeholder="Enter contact person phone" />
                                <p v-if="form.errors.contact_person_phone" class="mt-1 text-sm text-red-600">{{
                                    form.errors.contact_person_phone }}</p>
                            </div>

                            <div>
                                <label for="drug_licence_no" class="block text-sm font-medium text-gray-700">
                                    Drug License Number
                                </label>
                                <input id="drug_licence_no" v-model="form.drug_lisence_no" type="text"
                                    class="block w-full mt-1 p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    placeholder="Enter drug license number" />
                                <p v-if="form.errors.drug_lisence_no" class="mt-1 text-sm text-red-600">{{
                                    form.errors.drug_lisence_no }}</p>
                            </div>

                            <div>
                                <label for="supplier_address" class="block text-sm font-medium text-gray-700">
                                    Address
                                </label>
                                <textarea id="supplier_address" v-model="form.address" rows="3"
                                    class="block w-full mt-1 p-2 text-sm rounded-md shadow-sm border-slate-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    placeholder="Enter supplier address"></textarea>
                                <p v-if="form.errors.address" class="mt-1 text-sm text-red-600">{{ form.errors.address
                                    }}</p>
                            </div>
                        </div>

                        <div class="flex justify-end mt-6 space-x-3">
                            <button type="button" @click="closeModal"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 border border-gray-300 rounded-md shadow-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Cancel
                            </button>
                            <button type="submit" :disabled="form.processing"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                                :class="{ 'opacity-50': form.processing }">
                                {{ form.processing ? 'Creating...' : 'Create Supplier' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
