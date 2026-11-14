<script setup>
import { ref } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { displayResponse } from '@/responseMessage.js';

const props = defineProps({
    user: Object,
    modules: Array,
    assignedModules: Array,
});

const form = useForm({
    modules: [...(props.assignedModules ?? [])],
});

const toggleModule = (moduleId) => {
    const id = Number(moduleId);
    if (form.modules.map(Number).includes(id)) {
        form.modules = form.modules.filter((item) => Number(item) !== id);
        return;
    }
    form.modules = [...form.modules, id];
};

const isChecked = (moduleId) => form.modules.map(Number).includes(Number(moduleId));

const submit = () => {
    form.post(route('backend.admin.modules.update', props.user.id), {
        preserveScroll: true,
        onSuccess: (response) => displayResponse(response),
    });
};
</script>

<template>
    <BackendLayout>
        <div class="w-full p-4 bg-white rounded-md dark:bg-slate-900">
            <div class="mb-4">
                <h1 class="text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    Assign add-on modules for {{ props.user?.name ?? props.user?.email }}. Total Management stays independent of Pharmacy and Prescription.
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-3">
                <label
                    v-for="module in props.modules ?? []"
                    :key="module.id"
                    class="flex items-start gap-3 p-3 border rounded-md cursor-pointer hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                >
                    <input type="checkbox" class="mt-1 rounded border-slate-300" :checked="isChecked(module.id)" @change="toggleModule(module.id)" />
                    <div>
                        <div class="font-semibold dark:text-white">{{ module.name }}</div>
                        <div class="text-xs text-slate-500">{{ module.slug }}</div>
                        <div v-if="module.description" class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ module.description }}</div>
                    </div>
                </label>

                <div class="pt-2">
                    <PrimaryButton :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Modules' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </BackendLayout>
</template>
