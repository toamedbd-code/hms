

<script setup>
    import { ref, onMounted } from 'vue';
    import BackendLayout from '@/Layouts/BackendLayout.vue';
    import { Link, router, useForm, usePage } from '@inertiajs/vue3';
    import InputError from '@/Components/InputError.vue';
    import InputLabel from '@/Components/InputLabel.vue';
    import PrimaryButton from '@/Components/PrimaryButton.vue';
    import AlertMessage from '@/Components/AlertMessage.vue';
    import { displayResponse, displayWarning } from '@/responseMessage.js';

    const props = defineProps(['certificate', 'id']);

    const form = useForm({
        name: props.certificate?.name ?? '',
        certificate_type: props.certificate?.certificate_type ?? '',
        issue_date: props.certificate?.issue_date ?? '',
        reference_no: props.certificate?.reference_no ?? '',
        email: props.certificate?.email ?? '',
        details: props.certificate?.details ?? '',
        photo: null,
        photoPreview: props.certificate?.photo ?? null,
        _method: props.certificate?.id ? 'put' : 'post',
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
        const routeName = props.id ? route('backend.certificate.update', props.id) : route('backend.certificate.store');
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
                            :href="route('backend.certificate.index')"
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
                            <InputLabel for="certificate_type" value="Certificate Type" />
                            <input id="certificate_type"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.certificate_type" type="text" placeholder="Certificate Type" />
                            <InputError class="mt-2" :message="form.errors.certificate_type" />
                        </div>

                        <div class="col-span-1 md:col-span-1">
                            <InputLabel for="issue_date" value="Issue Date" />
                            <input id="issue_date"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.issue_date" type="date" />
                            <InputError class="mt-2" :message="form.errors.issue_date" />
                        </div>

                        <div class="col-span-1 md:col-span-1">
                            <InputLabel for="reference_no" value="Reference No" />
                            <input id="reference_no"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.reference_no" type="text" placeholder="Reference No" />
                            <InputError class="mt-2" :message="form.errors.reference_no" />
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <InputLabel for="email" value="Email" />
                            <input id="email"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.email" type="email" placeholder="Email" />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <InputLabel for="details" value="Details" />
                            <textarea id="details"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                                v-model="form.details" rows="3" placeholder="Certificate details"></textarea>
                            <InputError class="mt-2" :message="form.errors.details" />
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

