<script setup>
    import { computed } from 'vue';
    import BackendLayout from '@/Layouts/BackendLayout.vue';
    import { Link, useForm } from '@inertiajs/vue3';
    import InputError from '@/Components/InputError.vue';
    import InputLabel from '@/Components/InputLabel.vue';
    import PrimaryButton from '@/Components/PrimaryButton.vue';
    import AlertMessage from '@/Components/AlertMessage.vue';
    import { displayResponse, displayWarning } from '@/responseMessage.js';

    const props = defineProps(['birthdeathrecord', 'id']);

    const form = useForm({
        record_type: props.birthdeathrecord?.record_type ?? 'Birth',
        child_name: props.birthdeathrecord?.child_name ?? '',
        gender: props.birthdeathrecord?.gender ?? '',
        weight: props.birthdeathrecord?.weight ?? '',
        birth_date: props.birthdeathrecord?.birth_date ?? '',
        phone: props.birthdeathrecord?.phone ?? '',
        address: props.birthdeathrecord?.address ?? '',
        case_id: props.birthdeathrecord?.case_id ?? '',
        mother_name: props.birthdeathrecord?.mother_name ?? '',
        father_name: props.birthdeathrecord?.father_name ?? '',
        report: props.birthdeathrecord?.report ?? '',

        patient_name: props.birthdeathrecord?.patient_name ?? '',
        death_date: props.birthdeathrecord?.death_date ?? '',
        guardian_name: props.birthdeathrecord?.guardian_name ?? '',

        child_photo: null,
        child_photo_preview: props.birthdeathrecord?.child_photo ?? null,
        mother_photo: null,
        mother_photo_preview: props.birthdeathrecord?.mother_photo ?? null,
        father_photo: null,
        father_photo_preview: props.birthdeathrecord?.father_photo ?? null,
        attachment: null,
        attachment_preview: props.birthdeathrecord?.attachment ?? null,
        report_attachment: null,
        report_attachment_preview: props.birthdeathrecord?.report_attachment ?? null,
        _method: props.birthdeathrecord?.id ? 'put' : 'post',
    });

    const isBirth = computed(() => form.record_type === 'Birth');

    const handleFileChange = (event, fileField, previewField) => {
        const file = event.target.files[0] ?? null;
        form[fileField] = file;
        if (!file) return;

        if (file.type && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                form[previewField] = e.target?.result ?? null;
            };
            reader.readAsDataURL(file);
        } else {
            form[previewField] = file.name;
        }
    };


    const submit = () => {
        const routeName = props.id ? route('backend.birthdeathrecord.update', props.id) : route('backend.birthdeathrecord.store');
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
                            :href="route('backend.birthdeathrecord.index')"
                            class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-white bg-slate-600 rounded hover:bg-slate-700"
                        >
                            Back
                        </Link>
                    </div>
                </div>

                <form @submit.prevent="submit" class="p-4">
                    <AlertMessage />
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3 mb-4">
                        <div>
                            <InputLabel for="record_type" value="Record Type" />
                            <select id="record_type"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200"
                                v-model="form.record_type">
                                <option value="Birth">Add Birth Record</option>
                                <option value="Death">Add Death Record</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.record_type" />
                        </div>
                    </div>

                    <div v-if="isBirth" class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                        <div class="col-span-1 md:col-span-2">
                            <InputLabel for="child_name" value="Child Name *" />
                            <input id="child_name" v-model="form.child_name" type="text" placeholder="Child Name"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200" />
                            <InputError class="mt-2" :message="form.errors.child_name" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="gender" value="Gender *" />
                            <select id="gender" v-model="form.gender"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200">
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.gender" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="weight" value="Weight *" />
                            <input id="weight" v-model="form.weight" type="text" placeholder="Weight"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200" />
                            <InputError class="mt-2" :message="form.errors.weight" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="birth_date" value="Birth Date *" />
                            <input id="birth_date" v-model="form.birth_date" type="date"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200" />
                            <InputError class="mt-2" :message="form.errors.birth_date" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="phone" value="Phone" />
                            <input id="phone" v-model="form.phone" type="text" placeholder="Phone"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200" />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>

                        <div class="col-span-1">
                            <InputLabel for="case_id" value="Case ID" />
                            <input id="case_id" v-model="form.case_id" type="text" placeholder="Case ID"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200" />
                            <InputError class="mt-2" :message="form.errors.case_id" />
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <InputLabel for="address" value="Address" />
                            <textarea id="address" v-model="form.address" rows="2" placeholder="Address"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200"></textarea>
                            <InputError class="mt-2" :message="form.errors.address" />
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <InputLabel for="mother_name" value="Mother Name *" />
                            <input id="mother_name" v-model="form.mother_name" type="text" placeholder="Mother Name"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200" />
                            <InputError class="mt-2" :message="form.errors.mother_name" />
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <InputLabel for="father_name" value="Father Name" />
                            <input id="father_name" v-model="form.father_name" type="text" placeholder="Father Name"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200" />
                            <InputError class="mt-2" :message="form.errors.father_name" />
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <InputLabel for="child_photo" value="Child Photo" />
                            <input id="child_photo" type="file" accept="image/*" @change="handleFileChange($event, 'child_photo', 'child_photo_preview')"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200" />
                            <InputError class="mt-2" :message="form.errors.child_photo" />
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <InputLabel for="mother_photo" value="Mother Photo" />
                            <input id="mother_photo" type="file" accept="image/*" @change="handleFileChange($event, 'mother_photo', 'mother_photo_preview')"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200" />
                            <InputError class="mt-2" :message="form.errors.mother_photo" />
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <InputLabel for="father_photo" value="Father Photo" />
                            <input id="father_photo" type="file" accept="image/*" @change="handleFileChange($event, 'father_photo', 'father_photo_preview')"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200" />
                            <InputError class="mt-2" :message="form.errors.father_photo" />
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <InputLabel for="report_attachment" value="Attach Document Photo" />
                            <input id="report_attachment" type="file" @change="handleFileChange($event, 'report_attachment', 'report_attachment_preview')"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200" />
                            <InputError class="mt-2" :message="form.errors.report_attachment" />
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <InputLabel for="report" value="Report" />
                            <textarea id="report" v-model="form.report" rows="3" placeholder="Report"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200"></textarea>
                            <InputError class="mt-2" :message="form.errors.report" />
                        </div>
                    </div>

                    <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-4">
                        <div class="col-span-1 md:col-span-2">
                            <InputLabel for="case_id_death" value="Case ID *" />
                            <input id="case_id_death" v-model="form.case_id" type="text" placeholder="Case ID"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200" />
                            <InputError class="mt-2" :message="form.errors.case_id" />
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <InputLabel for="patient_name" value="Patient Name *" />
                            <input id="patient_name" v-model="form.patient_name" type="text" placeholder="Patient Name"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200" />
                            <InputError class="mt-2" :message="form.errors.patient_name" />
                        </div>

                        <div class="col-span-1 md:col-span-1">
                            <InputLabel for="death_date" value="Death Date *" />
                            <input id="death_date" v-model="form.death_date" type="date"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200" />
                            <InputError class="mt-2" :message="form.errors.death_date" />
                        </div>

                        <div class="col-span-1 md:col-span-1">
                            <InputLabel for="guardian_name" value="Guardian Name *" />
                            <input id="guardian_name" v-model="form.guardian_name" type="text" placeholder="Guardian Name"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200" />
                            <InputError class="mt-2" :message="form.errors.guardian_name" />
                        </div>

                        <div class="col-span-1 md:col-span-1">
                            <InputLabel for="attachment" value="Attachment" />
                            <input id="attachment" type="file" @change="handleFileChange($event, 'attachment', 'attachment_preview')"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200" />
                            <InputError class="mt-2" :message="form.errors.attachment" />
                        </div>

                        <div class="col-span-1 md:col-span-3">
                            <InputLabel for="report_death" value="Report" />
                            <textarea id="report_death" v-model="form.report" rows="3" placeholder="Report"
                                class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200"></textarea>
                            <InputError class="mt-2" :message="form.errors.report" />
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

