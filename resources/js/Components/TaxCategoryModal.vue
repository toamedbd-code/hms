<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    isOpen: Boolean,
    taxCategory: Object
})

const emit = defineEmits(['close', 'created', 'updated'])

const form = useForm({
    name: '',
    percentage: '',
})

// Watch for changes in taxCategory prop to populate form
watch(() => props.taxCategory, (newTaxCategory) => {
    if (newTaxCategory) {
        form.name = newTaxCategory.name || ''
        form.percentage = newTaxCategory.percentage || ''
    } else {
        form.reset()
    }
}, { immediate: true })

const submit = () => {
    const routeName = props.taxCategory?.id
        ? route('backend.chargetaxcategory.update', props.taxCategory.id)
        : route('backend.chargetaxcategory.store')

    form.transform(data => ({
        ...data,
        _method: props.taxCategory?.id ? 'put' : 'post',
    })).post(routeName, {
        onSuccess: (response) => {
            form.reset()
            emit(props.taxCategory?.id ? 'updated' : 'created', response)
            closeModal()
        },
        onError: (errors) => {
            // Form errors are automatically handled by Inertia
        },
    })
}

const closeModal = () => {
    form.reset()
    emit('close')
}
</script>


<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="closeModal"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div
                class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                <div class="sm:flex sm:items-start">
                    <div class="w-full mt-3 text-center sm:mt-0 sm:text-left">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">
                            {{ taxCategory?.id ? 'Edit Tax Category' : 'Add New Tax Category' }}
                        </h3>

                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <label for="modal-tax-name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Tax Name *
                                </label>
                                <input id="modal-tax-name" v-model="form.name" type="text"
                                    placeholder="Enter tax name (e.g., VAT, GST)"
                                    class="block w-full p-2 text-sm rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    required />
                                <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <div>
                                <label for="modal-percentage" class="block text-sm font-medium text-gray-700 mb-1">
                                    Tax Percentage *
                                </label>
                                <div class="relative">
                                    <input id="modal-percentage" v-model="form.percentage" type="number" step="0.01"
                                        min="0" max="100" placeholder="Enter percentage"
                                        class="block w-full p-2 pr-8 text-sm rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                        required />
                                    <span
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500">%</span>
                                </div>
                                <div v-if="form.errors.percentage" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.percentage }}
                                </div>
                            </div>

                            <div class="flex justify-end space-x-3 pt-4">
                                <button type="button" @click="closeModal"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="form.processing"
                                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50">
                                    {{ form.processing ? 'Saving...' : (taxCategory?.id ? 'Update' : 'Create') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
