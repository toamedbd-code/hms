

<script setup>
    import { ref, onMounted } from 'vue';
    import BackendLayout from '@/Layouts/BackendLayout.vue';
    import { Link, router, useForm, usePage } from '@inertiajs/vue3';
    import InputError from '@/Components/InputError.vue';
    import InputLabel from '@/Components/InputLabel.vue';
    import PrimaryButton from '@/Components/PrimaryButton.vue';
    import AlertMessage from '@/Components/AlertMessage.vue';
    import { displayResponse, displayWarning } from '@/responseMessage.js';

    const props = defineProps(['frontoffice', 'id']);

    const form = useForm({
        name: props.frontoffice?.name ?? '',
        phone: props.frontoffice?.phone ?? '',
        purpose: props.frontoffice?.purpose ?? '',
        visit_to: props.frontoffice?.visit_to ?? '',
        date_in: props.frontoffice?.date_in ?? '',
        time_in: props.frontoffice?.time_in ?? '',
        time_out: props.frontoffice?.time_out ?? '',
        photo: null,
        photoPreview: props.frontoffice?.photo ?? null,
        _method: props.frontoffice?.id ? 'put' : 'post',
    });

    const handlePhotoChange = (event) => {
        const file = event.target.files[0];
        form.photo = file;

        // Display photo preview
        const reader = new FileReader();
        reader.onload = (e) => {
            form.photoPreview = e.target.result;
        };
        reader.readAsDataURL(file);
    };

    const submit = () => {
        const routeName = props.id ? route('backend.frontoffice.update', props.id) : route('backend.frontoffice.store');
        form.transform(data => ({
            ...data,
            remember: '',
            isDirty: false,
        })).post(routeName, {

            onSuccess: (response) => {
                if (!props.id)
                    form.reset();
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
            <div
                class="w-full mt-3 transition duration-1000 ease-in-out transform bg-white border border-gray-700 rounded-md shadow-lg shadow-gray-800/50 dark:bg-slate-900">

                <div
                    class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md shadow-md dark:bg-gray-800 dark:text-gray-200 shadow-gray-800/50">
                    <div>
                        <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                    </div>
                    <div class="p-4 py-2">
                        <Link
                            :href="route('backend.frontoffice.index')"
                            class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-white bg-slate-600 rounded hover:bg-slate-700"
                        >
                            Back
                        </Link>
                    </div>
                </div>

                <form @submit.prevent="submit" class="p-4">
                    <AlertMessage />
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">

                        <div class="col-span-1 md:col-span-2">
                            <InputLabel for="photo" value="Photo" />
                            <div v-if="form.photoPreview">
                                <img :src="form.photoPreview" alt="Photo Preview" class="max-w-xs mt-2" height="60"
                                    width="60" />
                            </div>
                            <input id="photo" type="file" accept="image/*"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                @change="handlePhotoChange" />
                            <InputError class="mt-2" :message="form.errors.photo" />
                        </div>

                        <div class="col-span-1 md:col-span-1">
                            <InputLabel for="name" value="Name" />
                            <input id="name"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.name" type="text" placeholder="Name" />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div class="col-span-1 md:col-span-1">
                            <InputLabel for="purpose" value="Purpose" />
                            <input id="purpose"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.purpose" type="text" placeholder="Purpose" />
                            <InputError class="mt-2" :message="form.errors.purpose" />
                        </div>

                        <div class="col-span-1 md:col-span-1">
                            <InputLabel for="visit_to" value="Visit To" />
                            <input id="visit_to"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.visit_to" type="text" placeholder="Visit To" />
                            <InputError class="mt-2" :message="form.errors.visit_to" />
                        </div>

                        <div class="col-span-1 md:col-span-1">
                            <InputLabel for="phone" value="Phone Number" />
                            <input id="phone"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.phone" type="text" placeholder="Phone Number" />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>

                        <div class="col-span-1 md:col-span-1">
                            <InputLabel for="date_in" value="Date In" />
                            <input id="date_in"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.date_in" type="date" />
                            <InputError class="mt-2" :message="form.errors.date_in" />
                        </div>

                        <div class="col-span-1 md:col-span-1">
                            <InputLabel for="time_in" value="Time In" />
                            <input id="time_in"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.time_in" type="time" />
                            <InputError class="mt-2" :message="form.errors.time_in" />
                        </div>

                        <div class="col-span-1 md:col-span-1">
                            <InputLabel for="time_out" value="Time Out" />
                            <input id="time_out"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.time_out" type="time" />
                            <InputError class="mt-2" :message="form.errors.time_out" />
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

