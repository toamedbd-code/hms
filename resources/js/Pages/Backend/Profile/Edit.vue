<script setup>
import { ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps({
    adminData: {
        type: Object,
        default: () => ({}),
    },
    pageTitle: {
        type: String,
        default: 'My Profile',
    },
});

const page = usePage();
const photoPreview = ref(null);
const photoInput = ref(null);
const currentPhotoUrl = ref(props.adminData?.photo ?? null);

watch(() => props.adminData?.photo, (newPhoto) => {
    currentPhotoUrl.value = newPhoto || null;
}, { immediate: true });

const profileForm = useForm({
    first_name: props.adminData?.first_name ?? '',
    last_name: props.adminData?.last_name ?? '',
    email: props.adminData?.email ?? '',
    phone: props.adminData?.phone ?? '',
    photo: null,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const getStaffEditPasswordStorageKey = () => {
    const adminId = page.props?.auth?.admin?.id ?? 'new';
    return `admin-last-saved-password-${adminId}`;
};

const selectNewPhoto = () => {
    const file = photoInput.value?.files?.[0];

    if (!file) {
        photoPreview.value = null;
        profileForm.photo = null;
        return;
    }

    profileForm.photo = file;

    const reader = new FileReader();
    reader.onload = (e) => {
        photoPreview.value = e.target?.result || null;
    };
    reader.readAsDataURL(file);
};

const updateProfile = () => {
    profileForm.post(route('backend.profile.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: (response) => {
            const selectedPreview = photoPreview.value;
            if (selectedPreview) {
                currentPhotoUrl.value = selectedPreview;
            }

            displayResponse(response);

            router.visit(route('backend.profile.edit'), {
                preserveScroll: true,
                replace: true,
                onSuccess: () => {
                    profileForm.photo = null;
                    if (photoInput.value) {
                        photoInput.value.value = null;
                    }
                    photoPreview.value = null;
                },
            });
        },
        onError: (errors) => {
            displayWarning(errors);
        },
    });
};

const updatePassword = () => {
    passwordForm.post(route('backend.profile.password.update'), {
        preserveScroll: true,
        onSuccess: (response) => {
            if (typeof window !== 'undefined' && passwordForm.password) {
                window.localStorage.setItem(getStaffEditPasswordStorageKey(), passwordForm.password);
            }
            displayResponse(response);
        },
        onError: (errors) => {
            displayWarning(errors);
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

            <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-2">
                <form @submit.prevent="updateProfile" class="border rounded-md p-4">
                    <h2 class="text-lg font-semibold text-gray-700">Profile Information</h2>
                    <p class="mt-1 text-sm text-gray-500">Update your account details and profile picture.</p>

                    <div class="mt-4 flex items-center gap-4">
                        <div class="h-20 w-20 overflow-hidden rounded-full border border-gray-300 bg-gray-100">
                            <img
                                v-if="photoPreview || currentPhotoUrl"
                                :src="photoPreview || currentPhotoUrl"
                                alt="Profile photo"
                                class="h-full w-full object-cover"
                            >
                            <div v-else class="h-full w-full flex items-center justify-center text-xs text-gray-500">
                                No Photo
                            </div>
                        </div>
                        <div class="flex-1">
                            <InputLabel for="photo" value="Profile Photo" />
                            <input
                                id="photo"
                                ref="photoInput"
                                type="file"
                                accept="image/*"
                                class="mt-1 block w-full text-sm"
                                @change="selectNewPhoto"
                            >
                            <InputError :message="profileForm.errors.photo" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <InputLabel for="first_name" value="First Name" />
                        <TextInput id="first_name" v-model="profileForm.first_name" type="text" class="mt-1 block w-full" required />
                        <InputError :message="profileForm.errors.first_name" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="last_name" value="Last Name" />
                        <TextInput id="last_name" v-model="profileForm.last_name" type="text" class="mt-1 block w-full" />
                        <InputError :message="profileForm.errors.last_name" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" v-model="profileForm.email" type="email" class="mt-1 block w-full" required />
                        <InputError :message="profileForm.errors.email" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="phone" value="Phone" />
                        <TextInput id="phone" v-model="profileForm.phone" type="text" class="mt-1 block w-full" />
                        <InputError :message="profileForm.errors.phone" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <PrimaryButton :disabled="profileForm.processing" :class="{ 'opacity-25': profileForm.processing }">
                            Save Profile
                        </PrimaryButton>
                    </div>
                </form>

                <form @submit.prevent="updatePassword" class="border rounded-md p-4">
                    <h2 class="text-lg font-semibold text-gray-700">Change Password</h2>
                    <p class="mt-1 text-sm text-gray-500">Use your current password to set a new one.</p>

                    <div class="mt-4">
                        <InputLabel for="current_password" value="Current Password" />
                        <TextInput
                            id="current_password"
                            v-model="passwordForm.current_password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="current-password"
                            required
                        />
                        <InputError :message="passwordForm.errors.current_password" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="password" value="New Password" />
                        <TextInput
                            id="password"
                            v-model="passwordForm.password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            required
                        />
                        <InputError :message="passwordForm.errors.password" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <InputLabel for="password_confirmation" value="Confirm Password" />
                        <TextInput
                            id="password_confirmation"
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                            required
                        />
                        <InputError :message="passwordForm.errors.password_confirmation" class="mt-2" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <PrimaryButton :disabled="passwordForm.processing" :class="{ 'opacity-25': passwordForm.processing }">
                            Update Password
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </BackendLayout>
</template>
