<script setup>
import { ref, onMounted, computed } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps(['role', 'permissions', 'id']);

const form = useForm({
    name: props.role?.name ?? '',
    guard_name: props.role?.guard_name ?? 'admin',
    permission_ids: props.role?.permission_ids ?? [],
    _method: props.role?.id ? 'put' : 'post',
});


const submit = () => {
    const routeName = props.id ? route('backend.role.update', props.id) : route('backend.role.store');
    form.transform(data => ({
        ...data,
        remember: '',
        isDirty: false,
    })).post(routeName, {

        onSuccess: (response) => {
            if (!props.id)
                form.reset();
            displayResponse(response)
        },
        onError: (errorObject) => {

            displayWarning(errorObject);
        },
    });
};

const checkedPermissions = computed({
    get: () => form.permission_ids,
    set: (newValue) => form.permission_ids = newValue,
});

function formatLabel(label) {
    if (!label) return '';
    return label
        .replace(/[-_ ]/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
}

const goToRoleList = () => {
    router.visit(route('backend.role.index'));
}; 

</script>

<template>
    <BackendLayout>
        <div class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md ">

            <div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-2 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <button @click="goToRoleList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Role List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-4">
                <!-- <AlertMessage /> -->
                <!-- <pre>{{ form }}</pre> -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">

                    <div class="col-span-1 md:col-span-2">
                        <InputLabel for="name" value="Role Name" />
                        <input id="name"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.name" type="text" placeholder="Role Name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <!-- <div class="col-span-1 md:col-span-2">
                        <InputLabel for="guard_name" value="Guard Name" />
                        <input id="guard_name"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.guard_name" type="text" readonly placeholder="Guard Name" />
                        <InputError class="mt-2" :message="form.errors.bn_name" />
                    </div> -->

                </div>

                <div class="w-full mt-4">
                    <InputLabel for="Permissions" value="Permissions" class="text-black" />
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                        <template v-for="permissionInfo in permissions" :key="permissionInfo.id">
                            <div>
                                <ul class="ml-4">
                                    <li>
                                        <input v-model="checkedPermissions" :value="permissionInfo.id" type="checkbox"
                                            class="cursor-pointer" :id="'permission_' + permissionInfo.id" />
                                        <label :for="'permission_' + permissionInfo.id"
                                            class="ml-2 cursor-pointer font-bold"
                                            :class="checkedPermissions.includes(permissionInfo.id) ? 'text-green-500' : 'text-gray-700'">
                                            {{ formatLabel(permissionInfo.name) }}
                                        </label>

                                        <ul v-if="permissionInfo.child" class="ml-4">
                                            <template v-for="childInfo in permissionInfo.child" :key="childInfo.id">
                                                <li class="ml-4">
                                                    <input v-model="checkedPermissions" :value="childInfo.id"
                                                        type="checkbox" class="cursor-pointer"
                                                        :id="'permission_' + childInfo.id" />
                                                    <label :for="'permission_' + childInfo.id"
                                                        class="ml-2 cursor-pointer"
                                                        :class="checkedPermissions.includes(childInfo.id) ? 'text-green-500' : 'text-gray-700'">
                                                        {{ formatLabel(childInfo.name) }}
                                                    </label>

                                                    <!-- Grandchild Permissions -->
                                                    <ul v-if="childInfo.child" class="ml-4">
                                                        <template v-for="childChildInfo in childInfo.child"
                                                            :key="childChildInfo.id">
                                                            <li class="ml-4">
                                                                <input v-model="checkedPermissions"
                                                                    :value="childChildInfo.id" type="checkbox"
                                                                    class="cursor-pointer"
                                                                    :id="'permission_' + childChildInfo.id" />
                                                                <label :for="'permission_' + childChildInfo.id"
                                                                    class="ml-2 cursor-pointer"
                                                                    :class="checkedPermissions.includes(childChildInfo.id) ? 'text-green-500' : 'text-black-500'">
                                                                    {{ formatLabel(childChildInfo.name) }}
                                                                </label>
                                                            </li>
                                                        </template>
                                                    </ul>
                                                </li>
                                            </template>
                                        </ul>
                                    </li>
                                </ul>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex items-center justify-end mt-4">
                    <PrimaryButton type="submit" class="ms-4" :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing">
                        {{ ((props.id ?? false) ? 'Update' : 'Create') }}
                    </PrimaryButton>
                </div>
            </form>

        </div>
    </BackendLayout>
</template>
