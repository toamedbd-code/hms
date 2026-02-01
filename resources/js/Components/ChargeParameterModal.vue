<script setup>
import { ref, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import UnitModal from '@/Components/UnitModal.vue'; // 1. Import the UnitModal component
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps({
    show: Boolean,
    units: {
        type: Array,
        default: () => []
    },
    existingParameters: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['close', 'parameter-created']);

const form = useForm({
    name: '',
    referance_from: '',
    referance_to: '',
    pathology_unit_id: '',
    description: ''
});

// 2. State to control the UnitModal's visibility
const showUnitModal = ref(false);

// Check if parameter name already exists
const isDuplicate = computed(() => {
    if (!form.name) return false;
    return props.existingParameters.some(param => 
        param.name.toLowerCase() === form.name.toLowerCase()
    );
});

// Reset form when modal opens/closes
watch(() => props.show, (newVal) => {
    if (newVal) {
        form.reset();
    }
});

// 3. Method to open the UnitModal
const openUnitModal = () => {
    showUnitModal.value = true;
};

// 4. Method to close the UnitModal
const closeUnitModal = () => {
    showUnitModal.value = false;
};

// 5. Method to handle a new unit being created
const handleUnitCreated = (response) => {
    // Reload the page to get the updated list of units from the server.
    // The `only: ['units']` option makes this a partial reload, which is more efficient.
    router.reload({
        only: ['units'],
        onSuccess: () => {
            // After successful reload, if the server response includes a new unit,
            // we can select it automatically.
            const newUnitId = response.data?.id;
            if (newUnitId) {
                form.pathology_unit_id = newUnitId;
            }
        }
    });
};

const submit = () => {
    if (isDuplicate.value) {
        displayWarning({ message: 'A parameter with this name already exists.' });
        return;
    }

    form.post(route('backend.parameterofpathology.store'), {
        onSuccess: (response) => {
            form.reset();
            displayResponse(response);
            emit('parameter-created', response);
            emit('close');
        },
        onError: (errors) => {
            displayWarning(errors);
        }
    });
};

const close = () => {
    form.reset();
    emit('close');
};
</script>

<template>
    <Modal :show="show" @close="close" max-width="2xl">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Add New Charge Parameter
            </h2>

            <form @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-4">
                    <!-- Parameter Name -->
                    <div>
                        <InputLabel for="name" value="Parameter Name*" />
                        <input 
                            id="name" 
                            type="text" 
                            required
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.name"
                            :class="{ 'border-red-500': isDuplicate }"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                        <p v-if="isDuplicate" class="mt-1 text-sm text-red-600">
                            A parameter with this name already exists.
                        </p>
                    </div>

                    <!-- Reference Range -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="referance_from" value="Reference Range From*" />
                            <input 
                                id="referance_from" 
                                type="text" 
                                required
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.referance_from" 
                            />
                            <InputError class="mt-2" :message="form.errors.referance_from" />
                        </div>

                        <div>
                            <InputLabel for="referance_to" value="Reference Range To*" />
                            <input 
                                id="referance_to" 
                                type="text" 
                                required
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.referance_to" 
                            />
                            <InputError class="mt-2" :message="form.errors.referance_to" />
                        </div>
                    </div>

                    <!-- Unit with Add Button -->
                    <div class="relative">
                        <InputLabel for="pathology_unit_id" value="Unit*" />
                        <div class="flex items-center space-x-2">
                            <select 
                                id="pathology_unit_id" 
                                required
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600 flex-1"
                                v-model="form.pathology_unit_id"
                            >
                                <option value="">Select Unit</option>
                                <option v-for="unit in props.units" :key="unit.id" :value="unit.id">
                                    {{ unit.name }}
                                </option>
                            </select>
                            <button type="button" @click="openUnitModal"
                                class="flex-shrink-0 inline-flex items-center justify-center w-10 h-10 text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200"
                                title="Add New Unit">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6">
                                    </path>
                                </svg>
                            </button>
                        </div>
                        <InputError class="mt-2" :message="form.errors.pathology_unit_id" />
                    </div>

                    <!-- Description -->
                    <div>
                        <InputLabel for="description" value="Description" />
                        <textarea 
                            id="description"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.description" 
                            rows="3"
                        ></textarea>
                        <InputError class="mt-2" :message="form.errors.description" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <SecondaryButton @click="close">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton 
                        type="submit" 
                        :class="{ 'opacity-25': form.processing }" 
                        :disabled="form.processing || isDuplicate"
                    >
                        Create Parameter
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>

    <!-- 6. Integrate the UnitModal component -->
    <UnitModal :show="showUnitModal" :existing-units="units" @close="closeUnitModal" @unit-created="handleUnitCreated" />
</template>