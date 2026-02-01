<script setup>
import { ref, watch, nextTick } from 'vue';
import BackendLayout from '@/Layouts/BackendLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { displayResponse, displayWarning } from '@/responseMessage.js';
import { parse, format, isValid, differenceInYears, differenceInMonths, differenceInDays, subYears, subMonths, subDays } from 'date-fns';

const props = defineProps(['patient', 'id', 'tpas']);

const ageYears = ref('');
const ageMonths = ref('');
const ageDays = ref('');
const ageYearsInput = ref(null);
const ageMonthsInput = ref(null);
const ageDaysInput = ref(null);

// Initialize age fields from patient data
if (props.patient?.age) {
    const ageParts = props.patient.age.match(/(\d+)\s*year|(\d+)\s*month|(\d+)\s*day/gi) || [];
    ageParts.forEach(part => {
        if (part.includes('year')) ageYears.value = part.replace(/\D/g, '');
        if (part.includes('month')) ageMonths.value = part.replace(/\D/g, '');
        if (part.includes('day')) ageDays.value = part.replace(/\D/g, '');
    });
}


const form = useForm({
    name: props.patient?.name ?? '',
    guardian_name: props.patient?.guardian_name ?? '',
    gender: props.patient?.gender ?? '',
    dob: props.patient?.dob ?? '',
    blood_group: props.patient?.blood_group ?? '',
    marital_status: props.patient?.marital_status ?? '',
    photo: null,
    phone: props.patient?.phone ?? '',
    email: props.patient?.email ?? '',
    address: props.patient?.address ?? '',
    remarks: props.patient?.remarks ?? '',
    any_known_allergies: props.patient?.any_known_allergies ?? '',
    tpa_id: props.patient?.tpa_id ?? '',
    tpa_code: props.patient?.tpa_code ?? '',
    tpa_validity: props.patient?.tpa_validity ?? '',
    tpa_nid: props.patient?.tpa_nid ?? '',
    age: props.patient?.age ?? '',

    _method: props.id ? 'put' : 'post',
});

const handlePhotoChange = (event) => {
    const file = event.target.files[0];
    form.photo = file;

    const reader = new FileReader();
    reader.onload = (e) => {
        form.photoPreview = e.target.result;
    };
    reader.readAsDataURL(file);
};

const updatingFrom = ref(null);

// Watch for props.isOpen and reset form on close
watch(() => props.isOpen, (newValue) => {
    if (!newValue) {
        form.reset();
        ageYears.value = '';
        ageMonths.value = '';
        ageDays.value = '';
    } else {
        nextTick(() => {
            document.getElementById('name')?.focus();
        });
    }
});

// Watch for DOB changes and calculate age
watch(() => form.dob, (newDob) => {
    if (updatingFrom.value === 'age') return;
    updatingFrom.value = 'dob';

    if (newDob) {
        const birthDate = parse(newDob, 'yyyy-MM-dd', new Date());
        if (!isValid(birthDate)) return;

        const today = new Date();

        let years = differenceInYears(today, birthDate);
        let remainingDate = subYears(today, years);

        let months = differenceInMonths(remainingDate, birthDate);
        remainingDate = subMonths(remainingDate, months);

        let days = differenceInDays(remainingDate, birthDate);

        ageYears.value = years > 0 ? years.toString() : '';
        ageMonths.value = months > 0 ? months.toString() : '';
        ageDays.value = days > 0 ? days.toString() : '';
    } else {
        ageYears.value = '';
        ageMonths.value = '';
        ageDays.value = '';
    }

    updatingFrom.value = null;
});

// Watch for age component changes and calculate DOB
watch([ageYears, ageMonths, ageDays], ([years, months, days]) => {
    if (updatingFrom.value === 'dob') return;
    updatingFrom.value = 'age';

    const yearsNum = parseInt(years) || 0;
    const monthsNum = parseInt(months) || 0;
    const daysNum = parseInt(days) || 0;

    if (yearsNum === 0 && monthsNum === 0 && daysNum === 0) {
        form.dob = '';
        updatingFrom.value = null;
        return;
    }

    let dobDate = new Date();

    if (yearsNum > 0) dobDate = subYears(dobDate, yearsNum);
    if (monthsNum > 0) dobDate = subMonths(dobDate, monthsNum);
    if (daysNum > 0) dobDate = subDays(dobDate, daysNum);
    
    // Prevent DOB from being in the future
    if (dobDate > new Date()) {
      form.dob = '';
    } else {
      form.dob = format(dobDate, 'yyyy-MM-dd');
    }
    
    updatingFrom.value = null;
}, { deep: true });

// Auto-focus logic for age inputs
const handleAgeInput = (currentInput, nextInput) => {
    return (e) => {
        if (e.target.value.length >= 2 && nextInput) {
            nextTick(() => {
                nextInput.value.focus();
                nextInput.value.select();
            });
        }
    };
};

watch(() => form.tpa_id, (tpaId) => {
    if (tpaId) {
        const selectedTPA = props.tpas.find(tpa => tpa.id === Number(tpaId));
        if (selectedTPA) {
            form.tpa_code = selectedTPA.code;
        } else {
            form.tpa_code = '';
        }
    } else {
        form.tpa_code = '';
    }
});

const submit = () => {
    const yearsNum = parseInt(ageYears.value) || 0;
    const monthsNum = parseInt(ageMonths.value) || 0;
    const daysNum = parseInt(ageDays.value) || 0;

    form.age = [
        yearsNum > 0 ? `${yearsNum} year${yearsNum !== 1 ? 's' : ''}` : '',
        monthsNum > 0 ? `${monthsNum} month${monthsNum !== 1 ? 's' : ''}` : '',
        daysNum > 0 ? `${daysNum} day${daysNum !== 1 ? 's' : ''}` : ''
    ].filter(Boolean).join(' ');


    const routeName = props.id ? route('backend.patient.update', props.id) : route('backend.patient.store');
    form.transform(data => ({
        ...data,
        _method: props.id ? 'put' : 'post',
    })).post(routeName, {
        onSuccess: (response) => {
            if (!props.id) {
                form.reset();
                ageYears.value = '';
                ageMonths.value = '';
                ageDays.value = '';
            }
            displayResponse(response);
        },
        onError: (errorObject) => {
            displayWarning(errorObject);
        },
    });
};

const goToPatientList = () => {
    router.visit(route('backend.patient.index'));
};

</script>

<template>
    <BackendLayout>

        <div class="w-full bg-white border rounded-md">
            <div class="flex items-center justify-between w-full text-gray-700 bg-gray-100 rounded-md">
                <div>
                    <h1 class="p-4 text-xl font-bold dark:text-white">{{ $page.props.pageTitle }}</h1>
                </div>

                <div class="p-2 py-2 flex items-center space-x-2">
                    <div class="flex items-center space-x-3">
                        <button @click="goToPatientList"
                            class="inline-flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-blue-400 to-blue-600 border-0 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 active:scale-95 transform transition-all duration-150 ease-in-out hover:bg-gradient-to-r hover:from-blue-500 hover:to-blue-700 ml-2">
                            <svg class="w-4 h-4 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z">
                                </path>
                            </svg>
                            Patient List
                        </button>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="p-3 space-y-4">
                <AlertMessage />

                <div class="grid grid-cols-4 md:grid-cols-4 lg:grid-cols-4 gap-4">
                    <div>
                        <InputLabel for="name" value="Full Name" />
                        <input id="name" v-model="form.name" type="text" placeholder="Name" class="form-input" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="guardian_name" value="Guardian Name" />
                        <input id="guardian_name" v-model="form.guardian_name" type="text" placeholder="Guardian Name"
                            class="form-input" />
                        <InputError :message="form.errors.guardian_name" />
                    </div>

                    <div>
                        <InputLabel for="gender" value="Gender" />
                        <select id="gender" v-model="form.gender" class="form-input">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                        <InputError :message="form.errors.gender" />
                    </div>

                    <div>
                        <InputLabel for="photo" value="Photo" />
                        <div v-if="form.photoPreview" class="mt-2">
                            <img :src="form.photoPreview" alt="Photo Preview" class="h-10 w-10 object-cover rounded" />
                        </div>
                        <input id="photo" type="file" accept="image/*" @change="handlePhotoChange" class="form-input" />
                        <InputError :message="form.errors.photo" />
                    </div>

                    <div>
                        <InputLabel for="dob" value="Date of Birth" />
                        <input id="dob" v-model="form.dob" type="date" class="form-input" />
                        <InputError :message="form.errors.dob" />
                    </div>

                    <div>
                        <InputLabel value="Age" />
                        <div class="flex space-x-1">
                            <input ref="ageYearsInput" v-model="ageYears" @input="handleAgeInput(ageYearsInput, ageMonthsInput)" 
                                   type="number" min="0" max="120" class="w-16 form-input" placeholder="Y" 
                                   @focus="$event.target.select()" />
                            <span class="self-center text-gray-500">y</span>
                            <input ref="ageMonthsInput" v-model="ageMonths" @input="handleAgeInput(ageMonthsInput, ageDaysInput)" 
                                   type="number" min="0" max="11" class="w-16 form-input" placeholder="M" 
                                   @focus="$event.target.select()" />
                            <span class="self-center text-gray-500">m</span>
                            <input ref="ageDaysInput" v-model="ageDays" @input="handleAgeInput(ageDaysInput, null)" 
                                   type="number" min="0" max="30" class="w-16 form-input" placeholder="D" 
                                   @focus="$event.target.select()" />
                            <span class="self-center text-gray-500">d</span>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="blood_group" value="Blood Group" />
                        <select id="blood_group" v-model="form.blood_group" class="form-input">
                            <option value="">Select Blood Group</option>
                            <option value="A+">A+</option>
                            <option value="B+">B+</option>
                            <option value="O+">O+</option>
                            <option value="AB+">AB+</option>
                            <option value="A-">A-</option>
                            <option value="B-">B-</option>
                            <option value="O-">O-</option>
                            <option value="AB-">AB-</option>
                        </select>
                        <InputError :message="form.errors.blood_group" />
                    </div>

                    <div>
                        <InputLabel for="marital_status" value="Marital Status" />
                        <select id="marital_status" v-model="form.marital_status" class="form-input">
                            <option value="">Select Marital Status</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Widowed">Widowed</option>
                            <option value="Separated">Separated</option>
                            <option value="Not Specific">Not Specific</option>
                        </select>
                        <InputError :message="form.errors.marital_status" />
                    </div>

                    <div>
                        <InputLabel for="phone" value="Phone" />
                        <input id="phone" v-model="form.phone" type="text" placeholder="Phone Number"
                            class="form-input" />
                        <InputError :message="form.errors.phone" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Email" />
                        <input id="email" v-model="form.email" type="email" placeholder="Email" class="form-input" />
                        <InputError :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="address" value="Address" />
                        <input id="address" v-model="form.address" type="text" placeholder="Address"
                            class="form-input" />
                        <InputError :message="form.errors.address" />
                    </div>

                    <div>
                        <InputLabel for="remarks" value="Remarks" />
                        <textarea id="remarks" v-model="form.remarks" rows="2" class="form-input"></textarea>
                        <InputError :message="form.errors.remarks" />
                    </div>

                    <div>
                        <InputLabel for="any_known_allergies" value="Known Allergies" />
                        <textarea id="any_known_allergies" v-model="form.any_known_allergies" rows="2"
                            class="form-input"></textarea>
                        <InputError :message="form.errors.any_known_allergies" />
                    </div>

                    <div>
                        <InputLabel for="tpa_id" value="TPA ID" />
                        <select id="tpa_id"
                            class="block w-full p-2 text-sm rounded-md shadow-sm border-slate-300 dark:border-slate-500 dark:bg-slate-700 dark:text-slate-200 focus:border-indigo-300 dark:focus:border-slate-600"
                            v-model="form.tpa_id" placeholder="Select Role">
                            <option value="">--Select TPA--</option>
                            <template v-for="data in tpas">
                                <option :value="data.id">{{ data.name }}</option>
                            </template>
                        </select>
                        <InputError :message="form.errors.tpa_id" />
                    </div>

                    <div>
                        <InputLabel for="tpa_code" value="TPA ID" />
                        <input id="any_known_allergies" type="text" v-model="form.tpa_code" class="form-input" >
                        <InputError :message="form.errors.tpa_code" />
                    </div>

                    <div>
                        <InputLabel for="tpa_validity" value="TPA Validity" />
                        <input id="tpa_validity" type="date" v-model="form.tpa_validity" class="form-input">
                        <InputError :message="form.errors.tpa_validity" />
                    </div>

                    <div>
                        <InputLabel for="tpa_nid" value="National Identification Number" />
                        <input id="tpa_nid" type="number" v-model="form.tpa_nid" class="form-input">
                        <InputError :message="form.errors.tpa_nid" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <PrimaryButton type="submit" :disabled="form.processing" :class="{ 'opacity-25': form.processing }">
                        {{ props.id ? 'Update' : 'Create' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </BackendLayout>
</template>

<style scoped>
.form-input {
    @apply block w-full p-2 text-sm rounded-md border-slate-300 focus:border-indigo-300;
}
</style>