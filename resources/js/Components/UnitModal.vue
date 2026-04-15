<script setup>
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import { displayResponse, displayWarning, showToastIfNoFlash } from '@/responseMessage.js';

const props = defineProps({
    show: Boolean,
    existingUnits: {
        type: Array,
        default: () => []
    }
});

const emit = defineEmits(['close', 'unit-created']);

const form = useForm({
    name: ''
});

// Check if unit name already exists
const isDuplicate = computed(() => {
    if (!form.name) return false;
    return props.existingUnits.some(unit => 
        unit.name.toLowerCase() === form.name.toLowerCase()
    );
});

// Reset form when modal opens/closes
watch(() => props.show, (newVal) => {
    if (newVal) {
        form.reset();
    }
});

const submit = () => {
    if (isDuplicate.value) {
        displayWarning({ message: 'A unit with this name already exists.' });
        return;
    }

    form.post(route('backend.pathologyunit.store'), {
        onSuccess: (response) => {
            form.reset();
            showToastIfNoFlash(response);
            emit('unit-created', response);
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
    <Modal :show="show" @close="close" max-width="lg">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Add New Unit
            </h2>

            <form @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-4">
                    <!-- Unit Name -->
                    <div>
                        <InputLabel for="name" value="Unit Name*" />
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
                            A unit with this name already exists.
                        </p>
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
                        Create Unit
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>