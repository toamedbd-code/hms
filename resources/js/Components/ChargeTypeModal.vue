<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    isOpen: Boolean,
    chargeType: Object
})

const emit = defineEmits(['close', 'created', 'updated'])

const moduleOptions = [
    'Appointment', 'OPD', 'IPD', 'Pathology', 'Radiology', 'Blood Bank', 'Ambulance'
]

const form = useForm({
    name: '',
    modules: [],
})

watch(() => props.chargeType, (newChargeType) => {
    if (newChargeType) {
        form.name = newChargeType.name || ''
        form.modules = newChargeType.modules ? JSON.parse(newChargeType.modules) : []
    } else {
        form.reset()
    }
}, { immediate: true })

const toggleModule = (module) => {
    if (form.modules.includes(module)) {
        form.modules = form.modules.filter(m => m !== module)
    } else {
        form.modules.push(module)
    }
}

const submit = () => {
    const routeName = props.chargeType?.id
        ? route('backend.chargetype.update', props.chargeType.id)
        : route('backend.chargetype.store')

    form.transform(data => ({
        ...data,
        modules: JSON.stringify(data.modules),
        _method: props.chargeType?.id ? 'put' : 'post',
    })).post(routeName, {
        onSuccess: (response) => {
            form.reset()
            emit(props.chargeType?.id ? 'updated' : 'created', response)
            closeModal()
        },
        onError: (errors) => {
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
                            {{ chargeType?.id ? 'Edit Charge Type' : 'Add New Charge Type' }}
                        </h3>

                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <label for="modal-name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Charge Type Name *
                                </label>
                                <input id="modal-name" v-model="form.name" type="text"
                                    placeholder="Enter charge type name"
                                    class="block w-full p-2 text-sm rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    required />
                                <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Module *
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <div v-for="module in moduleOptions" :key="module" class="flex items-center">
                                        <input :id="'modal_module_' + module" type="checkbox" :value="module"
                                            :checked="form.modules.includes(module)" @change="toggleModule(module)"
                                            class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" />
                                        <label :for="'modal_module_' + module" class="ml-2 text-sm text-gray-700">
                                            {{ module }}
                                        </label>
                                    </div>
                                </div>
                                <div v-if="form.errors.modules" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.modules }}
                                </div>
                            </div>

                            <div class="flex justify-end space-x-3 pt-4">
                                <button type="button" @click="closeModal"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="form.processing"
                                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50">
                                    {{ form.processing ? 'Saving...' : (chargeType?.id ? 'Update' : 'Create') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
