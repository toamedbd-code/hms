<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    isOpen: Boolean,
    unitType: Object
})

const emit = defineEmits(['close', 'created', 'updated'])

const form = useForm({
    name: '',
})

// Watch for changes in unitType prop to populate form
watch(() => props.unitType, (newUnitType) => {
    if (newUnitType) {
        form.name = newUnitType.name || ''
    } else {
        form.reset()
    }
}, { immediate: true })

const submit = () => {
    const routeName = props.unitType?.id
        ? route('backend.chargeunittype.update', props.unitType.id)
        : route('backend.chargeunittype.store')

    form.transform(data => ({
        ...data,
        _method: props.unitType?.id ? 'put' : 'post',
    })).post(routeName, {
        onSuccess: (response) => {
            form.reset()
            emit(props.unitType?.id ? 'updated' : 'created', response)
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
                            {{ unitType?.id ? 'Edit Unit Type' : 'Add New Unit Type' }}
                        </h3>

                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <label for="modal-unit-name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Unit Name *
                                </label>
                                <input id="modal-unit-name" v-model="form.name" type="text"
                                    placeholder="Enter unit name (e.g., Per Hour, Per Day, Per Test)"
                                    class="block w-full p-2 text-sm rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    required />
                                <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <div class="flex justify-end space-x-3 pt-4">
                                <button type="button" @click="closeModal"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="form.processing"
                                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50">
                                    {{ form.processing ? 'Saving...' : (unitType?.id ? 'Update' : 'Create') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
