<script setup>
import { ref, watch, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { displayResponse, showToastIfNoFlash } from '@/responseMessage.js';
import { parse, format, isValid, differenceInYears, differenceInMonths, differenceInDays, subYears, subMonths, subDays } from 'date-fns';

const props = defineProps(['isOpen']);
const emit = defineEmits(['close', 'patientCreated']);

const form = useForm({
    name: '',
    gender: '',
    dob: '',
    blood_group: '',
    phone: '',
    email: '',
    address: '',
    remarks: '',
    age: '',
});

const ageYears = ref('');
const ageMonths = ref('');
const ageDays = ref('');
const ageYearsInput = ref(null);
const ageMonthsInput = ref(null);
const ageDaysInput = ref(null);

const closeModal = () => {
    form.reset();
    ageYears.value = '';
    ageMonths.value = '';
    ageDays.value = '';
    emit('close');
};

const submit = async () => {
    form.age = [
        ageYears.value ? `${ageYears.value} year${ageYears.value !== '1' ? 's' : ''}` : '',
        ageMonths.value ? `${ageMonths.value} month${ageMonths.value !== '1' ? 's' : ''}` : '',
        ageDays.value ? `${ageDays.value} day${ageDays.value !== '1' ? 's' : ''}` : ''
    ].filter(Boolean).join(' ');

    form.processing = true;
    form.clearErrors && form.clearErrors();

    const payload = {
        name: form.name,
        gender: form.gender,
        dob: form.dob,
        blood_group: form.blood_group,
        phone: form.phone,
        email: form.email,
        address: form.address,
        remarks: form.remarks,
        age: form.age,
    };

    try {
        const res = await axios.post(route('backend.patient.store'), payload, {
            headers: { Accept: 'application/json' }
        });

        const newPatient = res.data?.patient ?? null;
        const successMsg = res.data?.successMessage ?? null;

        if (successMsg) {
            showToastIfNoFlash({ props: { flash: { successMessage: successMsg } } });
        }

        form.reset();
        ageYears.value = '';
        ageMonths.value = '';
        ageDays.value = '';
        if (newPatient) emit('patientCreated', newPatient);
        closeModal();
    } catch (err) {
        if (err.response && err.response.status === 422) {
            form.errors = err.response.data.errors || {};
        } else {
            showToastIfNoFlash({ props: { flash: { errorMessage: err.response?.data?.errorMessage ?? 'Server error' } } });
        }
    } finally {
        form.processing = false;
    }
};

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

const updatingFrom = ref(null);

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
    
    if (dobDate > new Date()) {
      form.dob = '';
    } else {
      form.dob = format(dobDate, 'yyyy-MM-dd');
    }

    updatingFrom.value = null;
}, { deep: true });

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
</script>

<template>
    <Teleport to="body">
        <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="closeModal"></div>

                <div
                    class="inline-block w-full max-w-4xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-lg dark:bg-slate-900">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            Add New Patient
                        </h3>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="mt-4">
                        <form @submit.prevent="submit" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <InputLabel for="name" value="Full Name *" />
                                    <input id="name" v-model="form.name" type="text" required class="form-input" />
                                    <InputError :message="form.errors.name" />
                                </div>

                                <div>
                                    <InputLabel for="gender" value="Gender *" />
                                    <select id="gender" v-model="form.gender" required class="form-input">
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Others">Others</option>
                                    </select>
                                    <InputError :message="form.errors.gender" />
                                </div>

                                <div>
                                    <InputLabel for="phone" value="Phone *" />
                                    <input id="phone" v-model="form.phone" type="text" required class="form-input" />
                                    <InputError :message="form.errors.phone" />
                                </div>

                                <div>
                                    <InputLabel for="dob" value="Date of Birth" />
                                    <input id="dob" v-model="form.dob" type="date" class="form-input" />
                                    <InputError :message="form.errors.dob" />
                                </div>

                                <div class="flex items-end space-x-2">
                                    <div>
                                        <InputLabel value="Age" />
                                        <div class="flex space-x-1">
                                            <input ref="ageYearsInput" v-model="ageYears"
                                                @input="handleAgeInput(ageYearsInput, ageMonthsInput)" type="number"
                                                min="0" max="120" class="w-16 form-input" placeholder="Y"
                                                @focus="$event.target.select()" />
                                            <span class="self-center text-gray-500">y</span>
                                            <input ref="ageMonthsInput" v-model="ageMonths"
                                                @input="handleAgeInput(ageMonthsInput, ageDaysInput)" type="number"
                                                min="0" max="11" class="w-16 form-input" placeholder="M"
                                                @focus="$event.target.select()" />
                                            <span class="self-center text-gray-500">m</span>
                                            <input ref="ageDaysInput" v-model="ageDays"
                                                @input="handleAgeInput(ageDaysInput, null)" type="number" min="0"
                                                max="30" class="w-16 form-input" placeholder="D"
                                                @focus="$event.target.select()" />
                                            <span class="self-center text-gray-500">d</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <button type="button" @click="closeModal"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">
                                    Cancel
                                </button>
                                <PrimaryButton type="submit" :disabled="form.processing">
                                    {{ form.processing ? 'Creating...' : 'Create Patient' }}
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.form-input {
    @apply block w-full p-2 text-sm rounded-md shadow-sm border border-gray-300 focus:border-indigo-500 focus:ring-indigo-500;
}
</style>