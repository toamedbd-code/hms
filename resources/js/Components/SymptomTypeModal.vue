<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close', 'symptomTypeCreated']);

const showAlert = ref(false);

const form = useForm({
  name: '',
  _method: 'post',
});

const submit = () => {
  showAlert.value = true;

  form.post(route('backend.symptom-types.store'), {
    onSuccess: (response) => {
      showAlert.value = false;
      displayResponse(response);
      const createdName = form.name;
      form.reset();
      emit('symptomTypeCreated', createdName);
      closeModal();
    },
    onError: (errorObject) => {
      showAlert.value = false;
      displayWarning(errorObject);
    },
    onFinish: () => {
      showAlert.value = false;
    }
  });
};

const closeModal = () => {
  form.reset();
  form.clearErrors();
  showAlert.value = false;
  emit('close');
};

watch(() => props.isOpen, (newValue) => {
  if (newValue) {
    form.reset();
    form.clearErrors();
    showAlert.value = false;
  }
});
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
      <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true" @click="closeModal"></div>

      <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

      <div class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
        <div class="sm:flex sm:items-start">
          <div class="w-full mt-3 text-center sm:mt-0 sm:text-left">
            <h3 class="text-lg font-medium leading-6 text-gray-900" id="modal-title">
              Create Symptoms Type
            </h3>

            <div class="mt-4">
              <form @submit.prevent="submit">
                <AlertMessage v-if="showAlert" />

                <div class="space-y-4">
                  <div>
                    <InputLabel for="modal_symptom_type" value="Symptoms Type" />
                    <input
                      id="modal_symptom_type"
                      v-model="form.name"
                      type="text"
                      placeholder="Enter symptoms type"
                      class="block w-full p-2 text-sm border rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                      :class="{ 'border-red-500': form.errors.name }"
                    />
                    <InputError class="mt-1" :message="form.errors.name" />
                  </div>
                </div>

                <div class="mt-6 sm:flex sm:flex-row-reverse">
                  <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <svg v-if="form.processing" class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ form.processing ? 'Creating...' : 'Create Type' }}
                  </button>
                  <button
                    type="button"
                    @click="closeModal"
                    :disabled="form.processing"
                    class="inline-flex justify-center w-full px-4 py-2 mt-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    Cancel
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
