<script setup>
import { ref, onMounted } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AlertMessage from '@/Components/AlertMessage.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps(['referralperson', 'id', 'categories']);

const form = useForm({
    name: props.referralperson?.name ?? '',
    phone: props.referralperson?.phone ?? '',
    contact_person_name: props.referralperson?.contact_person_name ?? '',
    contact_person_phone: props.referralperson?.contact_person_phone ?? '',
    category_id: props.referralperson?.category_id ?? '',
    address: props.referralperson?.address ?? '',
    standard_commission: props.referralperson?.standard_commission ?? '',
    opd_commission: props.referralperson?.opd_commission ?? '',
    ipd_commission: props.referralperson?.ipd_commission ?? '',
    pharmacy_commission: props.referralperson?.pharmacy_commission ?? '',
    pathology_commission: props.referralperson?.pathology_commission ?? '',
    radiology_commission: props.referralperson?.radiology_commission ?? '',
    blood_bank_commission: props.referralperson?.blood_bank_commission ?? '',
    ambulance_commission: props.referralperson?.ambulance_commission ?? '',
    apply_to_all: props.referralperson?.apply_to_all ?? false,

    _method: props.referralperson?.id ? 'put' : 'post',
});

const submit = () => {
    const routeName = props.id ? route('backend.referralperson.update', props.id) : route('backend.referralperson.store');
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

const applyStandardCommissionToAll = () => {
    if (form.apply_to_all && form.standard_commission) {
        form.opd_commission = form.standard_commission;
        form.ipd_commission = form.standard_commission;
        form.pharmacy_commission = form.standard_commission;
        form.pathology_commission = form.standard_commission;
        form.radiology_commission = form.standard_commission;
        form.blood_bank_commission = form.standard_commission;
        form.ambulance_commission = form.standard_commission;
    }
};

const goToRefferalList = () => {
    router.get(route('backend.referralperson.index'));
};

</script>

<template>
    <BackendLayout>
        <div
            class="w-full transition duration-1000 ease-in-out transform bg-white rounded-md">
            <div
                class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md dark:bg-gray-800 dark:text-gray-200">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-2 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">

                        <button @click="goToRefferalList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Refferal List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-2">
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-2 text-xs">
                    <!-- LEFT SIDE (md:col-span-3) -->
                    <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-2 gap-2">
                        <div class="mb-2">
                            <InputLabel for="name" value="Referrer Name *" class="text-xs" />
                            <input id="name" class="form-input text-xs p-1 h-8" v-model="form.name" type="text"
                                placeholder="Referrer Name" required />
                            <InputError class="mt-1 text-xs" :message="form.errors.name" />
                        </div>
                        
                        <div class="mb-2">
                            <InputLabel for="phone" value="Referrer Phone *" class="text-xs" />
                            <input id="phone" class="form-input text-xs p-1 h-8" v-model="form.phone" type="text"
                                placeholder="Referrer phone" required />
                            <InputError class="mt-1 text-xs" :message="form.errors.phone" />
                        </div>

                        <div class="mb-2">
                            <InputLabel for="contact_person_name" value="Contact Person" class="text-xs" />
                            <input id="contact_person_name" class="form-input text-xs p-1 h-8"
                                v-model="form.contact_person_name" type="text" placeholder="Contact Person" />
                            <InputError class="mt-1 text-xs" :message="form.errors.contact_person_name" />
                        </div>

                        <div class="mb-2">
                            <InputLabel for="contact_person_phone" value="Contact Phone" class="text-xs" />
                            <input id="contact_person_phone" class="form-input text-xs p-1 h-8"
                                v-model="form.contact_person_phone" type="text" placeholder="Contact Phone" />
                            <InputError class="mt-1 text-xs" :message="form.errors.contact_person_phone" />
                        </div>

                        <div class="mb-2">
                            <InputLabel for="category_id" value="Category *" class="text-xs" />
                            <select id="category_id" class="form-input text-xs h-9" v-model="form.category_id"
                                required>
                                <option value="">Select Category</option>
                                <option v-for="data in categories" :key="data.id" :value="data.id">{{ data.name }}
                                </option>
                            </select>
                            <InputError class="mt-1 text-xs" :message="form.errors.category_id" />
                        </div>

                        <div class="md:col-span-2 mb-2">
                            <InputLabel for="address" value="Address" class="text-xs" />
                            <textarea id="address" class="form-input text-xs p-1 h-16" v-model="form.address"
                                placeholder="Address"></textarea>
                            <InputError class="mt-1 text-xs" :message="form.errors.address" />
                        </div>
                    </div>

                    <!-- RIGHT SIDE (md:col-span-1) -->
                    <div class="md:col-span-1 space-y-1">
                        <div>
                            <InputLabel for="standard_commission" value="Std Commission (%) *" class="text-xs" />
                            <input id="standard_commission" class="form-input text-xs p-1 h-8"
                                v-model="form.standard_commission" type="number" step="0.01" required />
                            <InputError class="mt-1 text-xs" :message="form.errors.standard_commission" />
                        </div>

                        <div class="flex items-center">
                            <input id="apply_to_all" type="checkbox" class="form-checkbox h-3 w-3"
                                v-model="form.apply_to_all" @change="applyStandardCommissionToAll" />
                            <label for="apply_to_all" class="ml-1 text-xs">Apply To All</label>
                        </div>

                        <div>
                            <InputLabel for="opd_commission" value="OPD (%)" class="text-xs" />
                            <input id="opd_commission" class="form-input text-xs p-1 h-8" v-model="form.opd_commission"
                                type="number" step="0.01" />
                            <InputError class="mt-1 text-xs" :message="form.errors.opd_commission" />
                        </div>

                        <div>
                            <InputLabel for="ipd_commission" value="IPD (%)" class="text-xs" />
                            <input id="ipd_commission" class="form-input text-xs p-1 h-8" v-model="form.ipd_commission"
                                type="number" step="0.01" />
                            <InputError class="mt-1 text-xs" :message="form.errors.ipd_commission" />
                        </div>

                        <div>
                            <InputLabel for="pharmacy_commission" value="Pharmacy (%)" class="text-xs" />
                            <input id="pharmacy_commission" class="form-input text-xs p-1 h-8"
                                v-model="form.pharmacy_commission" type="number" step="0.01" />
                            <InputError class="mt-1 text-xs" :message="form.errors.pharmacy_commission" />
                        </div>

                        <div>
                            <InputLabel for="pathology_commission" value="Pathology (%)" class="text-xs" />
                            <input id="pathology_commission" class="form-input text-xs p-1 h-8"
                                v-model="form.pathology_commission" type="number" step="0.01" />
                            <InputError class="mt-1 text-xs" :message="form.errors.pathology_commission" />
                        </div>

                        <div>
                            <InputLabel for="radiology_commission" value="Radiology (%)" class="text-xs" />
                            <input id="radiology_commission" class="form-input text-xs p-1 h-8"
                                v-model="form.radiology_commission" type="number" step="0.01" />
                            <InputError class="mt-1 text-xs" :message="form.errors.radiology_commission" />
                        </div>

                        <div>
                            <InputLabel for="blood_bank_commission" value="Blood Bank (%)" class="text-xs" />
                            <input id="blood_bank_commission" class="form-input text-xs p-1 h-8"
                                v-model="form.blood_bank_commission" type="number" step="0.01" />
                            <InputError class="mt-1 text-xs" :message="form.errors.blood_bank_commission" />
                        </div>

                        <div>
                            <InputLabel for="ambulance_commission" value="Ambulance (%)" class="text-xs" />
                            <input id="ambulance_commission" class="form-input text-xs p-1 h-8"
                                v-model="form.ambulance_commission" type="number" step="0.01" />
                            <InputError class="mt-1 text-xs" :message="form.errors.ambulance_commission" />
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-2 text-xs">
                    
                </div>
                

                <div class="flex justify-end mt-2">
                    <PrimaryButton type="submit" class="ms-2 text-xs px-3 py-1"
                        :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        {{ props.id ? 'Update' : 'Create' }}
                    </PrimaryButton>
                </div>
            </form>

        </div>
    </BackendLayout>
</template>

<style scoped>
.form-input {
    @apply block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600;
}

.form-checkbox {
    @apply rounded border-slate-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:border-slate-500 dark:bg-slate-700;
}
</style>