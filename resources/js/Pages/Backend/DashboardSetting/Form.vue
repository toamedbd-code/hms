<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps({
  dashboardSetting: {
    type: Object,
    default: () => ({}),
  },
  pageTitle: {
    type: String,
    default: 'Dashboard Filter Settings',
  },
});

const form = useForm({
  filter_type: props.dashboardSetting?.filter_type ?? 'daily',
  filter_from: props.dashboardSetting?.filter_from ?? '',
  filter_to: props.dashboardSetting?.filter_to ?? '',
});

watch(() => form.filter_type, (newType) => {
  if (newType !== 'custom') {
    form.filter_from = '';
    form.filter_to = '';
  }
});

const submit = () => {
  form.post(route('backend.dashboard-setting.update'), {
    onSuccess: (response) => {
      displayResponse(response);
    },
    onError: (errorObject) => {
      displayWarning(errorObject);
    },
  });
};
</script>

<template>
  <BackendLayout>
    <div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md">
      <div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
        <div>
          <h1 class="p-4 text-xl font-bold dark:text-white">{{ pageTitle }}</h1>
        </div>
        <div class="p-4 py-2"></div>
      </div>

      <form @submit.prevent="submit" class="p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="col-span-1">
            <InputLabel for="filter_type" value="Default Dashboard Filter" />
            <select
              id="filter_type"
              v-model="form.filter_type"
              class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
            >
              <option value="daily">Daily</option>
              <option value="monthly">Monthly</option>
              <option value="yearly">Yearly</option>
              <option value="custom">Custom (Date Range)</option>
            </select>
            <InputError class="mt-2" :message="form.errors.filter_type" />
            <p class="text-xs text-gray-500 mt-1">Dashboard will load using this filter by default.</p>
          </div>
        </div>

        <div v-if="form.filter_type === 'custom'" class="grid grid-cols-1 gap-4 sm:grid-cols-2 mt-4">
          <div class="col-span-1">
            <InputLabel for="filter_from" value="From Date" />
            <input
              id="filter_from"
              v-model="form.filter_from"
              type="date"
              class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
            />
            <InputError class="mt-2" :message="form.errors.filter_from" />
          </div>

          <div class="col-span-1">
            <InputLabel for="filter_to" value="To Date" />
            <input
              id="filter_to"
              v-model="form.filter_to"
              type="date"
              class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
            />
            <InputError class="mt-2" :message="form.errors.filter_to" />
          </div>
        </div>

        <div class="flex items-center justify-end mt-6">
          <PrimaryButton type="submit" class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
            Save Settings
          </PrimaryButton>
        </div>
      </form>
    </div>
  </BackendLayout>
</template>
