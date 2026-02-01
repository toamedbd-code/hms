<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    isOpen: Boolean,
    chargeCategory: Object,
    chargeTypes: Array
})

const emit = defineEmits(['close', 'created', 'updated'])

const form = useForm({
    charge_type_id: '',
    name: '',
    description: '',
})

// Watch for changes in chargeCategory prop to populate form
watch(() => props.chargeCategory, (newChargeCategory) => {
    if (newChargeCategory) {
        form.charge_type_id = newChargeCategory.charge_type_id || ''
        form.name = newChargeCategory.name || ''
        form.description = newChargeCategory.description || ''
    } else {
        form.reset()
    }
}, { immediate: true })

const submit = () => {
    const routeName = props.chargeCategory?.id
        ? route('backend.chargecategory.update', props.chargeCategory.id)
        : route('backend.chargecategory.store')

    form.transform(data => ({
        ...data,
        _method: props.chargeCategory?.id ? 'put' : 'post',
    })).post(routeName, {
        onSuccess: (response) => {
            form.reset()
            emit(props.chargeCategory?.id ? 'updated' : 'created', response)
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
                            {{ chargeCategory?.id ? 'Edit Charge Category' : 'Add New Charge Category' }}
                        </h3>

                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <label for="modal-charge-type" class="block text-sm font-medium text-gray-700 mb-1">
                                    Charge Type *
                                </label>
                                <select id="modal-charge-type" v-model="form.charge_type_id"
                                    class="block w-full p-2 text-sm rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    required>
                                    <option value="">Select Charge Type</option>
                                    <option v-for="chargeType in chargeTypes" :key="chargeType.id"
                                        :value="chargeType.id">
                                        {{ chargeType.name }}
                                    </option>
                                </select>
                                <div v-if="form.errors.charge_type_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.charge_type_id }}
                                </div>
                            </div>

                            <div>
                                <label for="modal-category-name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Category Name *
                                </label>
                                <input id="modal-category-name" v-model="form.name" type="text"
                                    placeholder="Enter category name"
                                    class="block w-full p-2 text-sm rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    required />
                                <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <div>
                                <label for="modal-description" class="block text-sm font-medium text-gray-700 mb-1">
                                    Description *
                                </label>
                                <textarea id="modal-description" v-model="form.description" rows="3"
                                    placeholder="Enter description"
                                    class="block w-full p-2 text-sm rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    required></textarea>
                                <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.description }}
                                </div>
                            </div>

                            <div class="flex justify-end space-x-3 pt-4">
                                <button type="button" @click="closeModal"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="form.processing"
                                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50">
                                    {{ form.processing ? 'Saving...' : (chargeCategory?.id ? 'Update' : 'Create') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
