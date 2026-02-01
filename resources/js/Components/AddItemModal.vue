<script setup>
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';
import { ref } from 'vue';

const props = defineProps({
    show: Boolean,
    title: String,
    inputLabel: String,
    inputId: String,
    form: Object,
    routeName: String,
    reloadOnly: Array
});

const emit = defineEmits(['close']);

const closeModal = () => {
    emit('close');
};

const submit = () => {
    props.form.post(route(props.routeName), {
        onSuccess: (response) => {
            props.form.reset();
            closeModal();
            displayResponse(response);
            if (props.reloadOnly) {
                router.reload({ only: props.reloadOnly });
            }
        }
    });
};
</script>

<template>
    <Modal :show="show" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Add New {{ title }}
            </h2>
            
            <div class="mt-4">
                <InputLabel :for="inputId" :value="inputLabel" />
                <input 
                    :id="inputId" 
                    v-model="form.name" 
                    type="text" 
                    class="block w-full mt-1 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" 
                    @keyup.enter="submit"
                />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>
            
            <div class="flex justify-end mt-6">
                <SecondaryButton @click="closeModal">Cancel</SecondaryButton>
                <PrimaryButton class="ml-3" @click="submit" :disabled="form.processing">
                    Save
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
