<script setup>
import { computed } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';

const props = defineProps(['referralperson', 'id', 'categories', 'mainCategories']);

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
    ecg_commission: props.referralperson?.ecg_commission ?? '',
    ultrasound_commission: props.referralperson?.ultrasound_commission ?? '',
    blood_bank_commission: props.referralperson?.blood_bank_commission ?? '',
    ambulance_commission: props.referralperson?.ambulance_commission ?? '',
    apply_to_all: props.referralperson?.apply_to_all ?? false,
    use_item_referral: props.referralperson?.use_item_referral ?? false,
    category_commissions: props.referralperson?.category_commissions ?? {},
    _method: props.referralperson?.id ? 'put' : 'post',
});

const submit = () => {
    const routeName = props.id
        ? route('backend.referralperson.update', props.id)
        : route('backend.referralperson.store');
    form.transform(data => ({ ...data, remember: '', isDirty: false }))
        .post(routeName, {
            onSuccess: (response) => {
                if (!props.id) form.reset();
                displayResponse(response);
            },
            onError: (errorObject) => displayWarning(errorObject),
        });
};

const applyStandardCommissionToAll = () => {
    if (form.apply_to_all && form.standard_commission) {
        form.opd_commission = form.standard_commission;
        form.ipd_commission = form.standard_commission;
        form.pharmacy_commission = form.standard_commission;
        form.pathology_commission = form.standard_commission;
        form.radiology_commission = form.standard_commission;
        form.ecg_commission = form.standard_commission;
        form.ultrasound_commission = form.standard_commission;
        form.blood_bank_commission = form.standard_commission;
        form.ambulance_commission = form.standard_commission;
        (props.mainCategories ?? []).forEach(catName => {
            form.category_commissions[catName] = form.standard_commission;
        });
    }
};

const goToRefferalList = () => router.get(route('backend.referralperson.index'));

const goBack = () => {
    if (window.history.length > 1) { window.history.back(); return; }
    router.visit(route('backend.referralperson.index'));
};

const groupedCategories = computed(() => {
    const map = {};
    (props.categories ?? []).forEach(c => {
        const g = c.group ?? '';
        if (!map[g]) map[g] = [];
        map[g].push(c);
    });
    return map;
});
</script>

<template>
    <BackendLayout>
        <div class="w-full bg-white rounded-md dark:bg-gray-900">

            <!-- Header bar -->
            <div class="flex items-center justify-between bg-gray-100 dark:bg-gray-800 rounded-t-md px-4 py-2">
                <h1 class="text-base font-bold text-gray-700 dark:text-white">{{ $page.props.pageTitle }}</h1>
                <div class="flex items-center gap-2">
                    <button type="button" @click="goBack"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-red-600 rounded shadow hover:bg-red-700 active:scale-95 transition-all">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </button>
                    <button type="button" @click="goToRefferalList"
                        class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 rounded shadow hover:from-blue-500 hover:to-blue-700 active:scale-95 transition-all">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        Refferal List
                    </button>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-3 space-y-3">

                <!-- ── Section 1: Referral Information ── -->
                <div class="section-card">
                    <div class="section-header">Referral Information</div>
                    <div class="p-3 grid grid-cols-2 md:grid-cols-3 gap-x-3 gap-y-2">
                        <div>
                            <InputLabel for="name" value="Referrer Name" class="field-label" />
                            <input id="name" class="form-input" v-model="form.name" type="text" placeholder="Referrer Name" />
                            <InputError class="mt-0.5 text-xs" :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="phone" value="Referrer Phone" class="field-label" />
                            <input id="phone" class="form-input" v-model="form.phone" type="text" placeholder="Referrer Phone" />
                            <InputError class="mt-0.5 text-xs" :message="form.errors.phone" />
                        </div>
                        <div>
                            <InputLabel for="category_id" value="Category" class="field-label" />
                            <select id="category_id" class="form-input" v-model="form.category_id">
                                <option value="">Select Category</option>
                                <template v-for="(groupItems, group) in groupedCategories" :key="group">
                                    <optgroup v-if="group" :label="group">
                                        <option v-for="item in groupItems" :key="item.id" :value="item.id">{{ item.name }}</option>
                                    </optgroup>
                                    <template v-else>
                                        <option v-for="item in groupItems" :key="item.id" :value="item.id">{{ item.name }}</option>
                                    </template>
                                </template>
                            </select>
                            <InputError class="mt-0.5 text-xs" :message="form.errors.category_id" />
                        </div>
                        <div>
                            <InputLabel for="contact_person_name" value="Contact Person" class="field-label" />
                            <input id="contact_person_name" class="form-input" v-model="form.contact_person_name" type="text" placeholder="Contact Person" />
                            <InputError class="mt-0.5 text-xs" :message="form.errors.contact_person_name" />
                        </div>
                        <div>
                            <InputLabel for="contact_person_phone" value="Contact Phone" class="field-label" />
                            <input id="contact_person_phone" class="form-input" v-model="form.contact_person_phone" type="text" placeholder="Contact Phone" />
                            <InputError class="mt-0.5 text-xs" :message="form.errors.contact_person_phone" />
                        </div>
                        <div>
                            <InputLabel for="address" value="Address" class="field-label" />
                            <input id="address" class="form-input" v-model="form.address" type="text" placeholder="Address" />
                            <InputError class="mt-0.5 text-xs" :message="form.errors.address" />
                        </div>
                    </div>
                </div>

                <!-- ── Section 2: Commission / Percentage Information ── -->
                <div class="section-card">
                    <div class="section-header">Commission / Percentage Information</div>
                    <div class="p-3 space-y-3">

                        <!-- Standard commission + option toggles -->
                        <div class="flex flex-wrap items-end gap-4 border-b border-gray-100 dark:border-gray-700 pb-3">
                            <div class="w-40">
                                <InputLabel for="standard_commission" value="Std Commission (%)" class="field-label" />
                                <input id="standard_commission" class="form-input" v-model="form.standard_commission"
                                    type="number" step="0.01" min="0" placeholder="0.00" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.standard_commission" />
                            </div>
                            <label for="apply_to_all" class="toggle-label">
                                <input type="checkbox" id="apply_to_all" class="form-checkbox"
                                    v-model="form.apply_to_all"
                                    @change="applyStandardCommissionToAll" />
                                <span>Apply To All</span>
                            </label>
                            <label for="use_item_referral" class="toggle-label">
                                <input type="checkbox" id="use_item_referral" class="form-checkbox"
                                    v-model="form.use_item_referral" />
                                <span>Use Item-level Percentage</span>
                            </label>
                        </div>

                        <!-- Commission fields grid -->
                        <div class="grid grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-x-3 gap-y-2">
                            <div>
                                <InputLabel for="opd_commission" value="OPD (%)" class="field-label" />
                                <input id="opd_commission" class="form-input" v-model="form.opd_commission" type="number" step="0.01" min="0" placeholder="0.00" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.opd_commission" />
                            </div>
                            <div>
                                <InputLabel for="ipd_commission" value="IPD (%)" class="field-label" />
                                <input id="ipd_commission" class="form-input" v-model="form.ipd_commission" type="number" step="0.01" min="0" placeholder="0.00" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.ipd_commission" />
                            </div>
                            <div>
                                <InputLabel for="pharmacy_commission" value="Pharmacy (%)" class="field-label" />
                                <input id="pharmacy_commission" class="form-input" v-model="form.pharmacy_commission" type="number" step="0.01" min="0" placeholder="0.00" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.pharmacy_commission" />
                            </div>
                            <div>
                                <InputLabel for="pathology_commission" value="Pathology (%)" class="field-label" />
                                <input id="pathology_commission" class="form-input" v-model="form.pathology_commission" type="number" step="0.01" min="0" placeholder="0.00" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.pathology_commission" />
                            </div>
                            <div>
                                <InputLabel for="radiology_commission" value="Radiology (%)" class="field-label" />
                                <input id="radiology_commission" class="form-input" v-model="form.radiology_commission" type="number" step="0.01" min="0" placeholder="0.00" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.radiology_commission" />
                            </div>
                            <div>
                                <InputLabel for="ecg_commission" value="ECG (%)" class="field-label" />
                                <input id="ecg_commission" class="form-input" v-model="form.ecg_commission" type="number" step="0.01" min="0" placeholder="0.00" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.ecg_commission" />
                            </div>
                            <div>
                                <InputLabel for="ultrasound_commission" value="Ultrasound (%)" class="field-label" />
                                <input id="ultrasound_commission" class="form-input" v-model="form.ultrasound_commission" type="number" step="0.01" min="0" placeholder="0.00" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.ultrasound_commission" />
                            </div>
                            <div>
                                <InputLabel for="blood_bank_commission" value="Blood Bank (%)" class="field-label" />
                                <input id="blood_bank_commission" class="form-input" v-model="form.blood_bank_commission" type="number" step="0.01" min="0" placeholder="0.00" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.blood_bank_commission" />
                            </div>
                            <div>
                                <InputLabel for="ambulance_commission" value="Ambulance (%)" class="field-label" />
                                <input id="ambulance_commission" class="form-input" v-model="form.ambulance_commission" type="number" step="0.01" min="0" placeholder="0.00" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors.ambulance_commission" />
                            </div>

                            <!-- Dynamic category type commissions -->
                            <div v-for="catName in (props.mainCategories ?? [])" :key="catName">
                                <InputLabel :for="'cc_' + catName" :value="catName + ' (%)'" class="field-label" />
                                <input :id="'cc_' + catName" class="form-input"
                                    v-model="form.category_commissions[catName]"
                                    type="number" step="0.01" min="0" placeholder="0.00" />
                                <InputError class="mt-0.5 text-xs" :message="form.errors['category_commissions.' + catName]" />
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end">
                    <PrimaryButton type="submit" class="px-6 py-2 text-xs"
                        :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        {{ props.id ? 'Update' : 'Create' }}
                    </PrimaryButton>
                </div>

            </form>
        </div>
    </BackendLayout>
</template>

<style scoped>
@reference "../../../../css/app.css";

.section-card {
    @apply border border-gray-200 dark:border-gray-700 rounded-md overflow-hidden;
}

.section-header {
    @apply bg-gray-50 dark:bg-gray-700 px-3 py-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide border-b border-gray-200 dark:border-gray-600;
}

.field-label {
    @apply block text-xs font-medium text-gray-600 dark:text-gray-300 mb-0.5;
}

.form-input {
    @apply block w-full px-2 py-1 text-xs rounded border border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200 dark:focus:border-slate-500 outline-none transition;
}

.form-checkbox {
    @apply h-4 w-4 rounded border-gray-400 text-indigo-600 cursor-pointer focus:ring-indigo-300 dark:border-slate-500 dark:bg-slate-700;
}

.toggle-label {
    @apply flex items-center gap-2 cursor-pointer select-none text-xs font-medium text-gray-700 dark:text-gray-300 pb-1;
}
</style>
