<script setup>
import { ref, onMounted } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps(['bed', 'id', 'bedTypes', 'bedGroups']);

const form = useForm({
    name: props.bed?.name ?? '',
    bed_type_id: props.bed?.bed_type_id ?? '',
    bed_group_id: props.bed?.bed_group_id ?? '',
    
    _method: props.bed?.id ? 'put' : 'post',
});

const submit = () => {
    const routeName = props.id ? route('backend.bed.update', props.id) : route('backend.bed.store');
    form.post(routeName, {
        onSuccess: (response) => {
            if (!props.id) form.reset();
            displayResponse(response);
        },
        onError: (errorObject) => {
            displayWarning(errorObject);
        },
    });
};

const goToBedList = () => {
    router.visit(route('backend.bed.index'));
};

</script>

<template>
    <BackendLayout>
        <div
            class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md">
            <div
                class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-2 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <button @click="goToBedList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Bed List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">
                <AlertMessage />

                <div class="w-full grid grid-cols-1 gap-6 md:grid-cols-3">
                    <!-- Name Field -->
                    <div>
                        <InputLabel for="name" value="Bed Name" class="required" />
                        <input id="name" v-model="form.name" type="text" required
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            placeholder="Enter bed name (e.g., Bed 101)" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <!-- Bed Type Selection -->
                    <div>
                        <InputLabel for="bed_type_id" value="Bed Type" class="required" />
                        <select id="bed_type_id" v-model="form.bed_type_id" required
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                            <option value="">Select Bed Type</option>
                            <option v-for="bedType in bedTypes" :key="bedType.id" :value="bedType.id">
                                {{ bedType.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.bed_type_id" />
                    </div>

                    <!-- Bed Group Selection -->
                    <div>
                        <InputLabel for="bed_group_id" value="Bed Group" class="required" />
                        <select id="bed_group_id" v-model="form.bed_group_id" required
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600">
                            <option value="">Select Bed Group</option>
                            <option v-for="bedGroup in bedGroups" :key="bedGroup.id" :value="bedGroup.id">
                                {{ bedGroup.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.bed_group_id" />
                    </div>

                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end mt-6">
                    <PrimaryButton type="submit" class="ms-4" :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing">
                        {{ (props.id ? 'Update' : 'Create') }} Bed
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </BackendLayout>
</template>

<style scoped>
.required::after {
    content: " *";
    color: #e53e3e;
}
</style>