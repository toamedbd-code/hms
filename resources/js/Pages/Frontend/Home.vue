<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const page = usePage();

// Prefer page-specific `websetting` when present (used by WebSetting form partials),
// otherwise fall back to the shared `webSetting` shared prop.
const webSetting = computed(() => page.props.websetting ?? page.props.webSetting ?? {});
const doctors = computed(() => page.props.featuredDoctors ?? []);
const bookingDoctors = computed(() => page.props.bookingDoctors ?? []);
const serviceItems = computed(() => page.props.serviceItems ?? []);
const facilityItems = computed(() => page.props.facilityItems ?? []);
const initialSection = computed(() => String(page.props.initialSection ?? 'home'));
const normalizedSection = computed(() => {
    const section = String(initialSection.value || 'home').trim().toLowerCase();
    const allowed = ['home', 'services', 'doctors', 'facilities', 'appointment', 'contact'];
    return allowed.includes(section) ? section : 'home';
});

const appointmentForm = useForm({
    patient_name: '',
    patient_phone: '',
    patient_email: '',
    doctor_id: '',
    appointment_date: '',
    message: '',
    website_url: '',
});

const localSuccess = ref('');
const localError = ref('');
const languageStorageKey = 'frontend.website.language';

const getServerLocale = () => {
    const locale = String(page.props?.locale ?? '').toLowerCase();
    return locale === 'en' || locale === 'bn' ? locale : '';
};

const getInitialLanguage = () => {
    const fromServer = getServerLocale();
    if (fromServer) {
        return fromServer;
    }

    if (typeof window === 'undefined') {
        return 'en';
    }

    const saved = window.localStorage.getItem(languageStorageKey);
    return saved === 'en' || saved === 'bn' ? saved : 'en';
};

const currentLang = ref(getInitialLanguage());

const copy = {
    en: {
        emergency: 'Emergency',
        book: 'Book Appointment',
        login: 'Software Login',
        nav: {
            home: 'Home',
            services: 'Services',
            doctors: 'Doctors',
            facilities: 'Facilities',
            appointment: 'Appointment',
            contact: 'Contact',
        },
        heroSub: 'Reliable diagnostics, specialist consultation and patient-first care under one roof.',
        heroBtn: 'Take Appointment',
        section: {
            home: 'About',
            services: 'Our Services',
            doctors: 'Our Doctors',
            facilities: 'Facilities',
            appointment: 'Online Appointment',
            contact: 'Contact Information',
        },
        contactCard: {
            phone: 'Phone',
            emergency: 'Emergency',
            address: 'Address',
        },
        doctor: {
            specialist: 'Specialist Doctor',
        },
        common: {
            na: 'N/A',
        },
        testimonials: 'Testimonials',
        role: 'Role',
        form: {
            name: 'Patient Name',
            phone: 'Phone Number',
            email: 'Email (Optional)',
            doctor: 'Select Doctor',
            date: 'Appointment Date & Time',
            message: 'Message',
            submit: 'Submit Appointment',
            processing: 'Submitting...',
            placeholderDoctor: 'Choose Doctor',
            placeholderMessage: 'Write your concern',
        },
        status: {
            ok: 'Appointment request submitted successfully.',
            err: 'Please check the form and try again.',
        },
    },
    bn: {
        emergency: 'জরুরি',
        book: 'অ্যাপয়েন্টমেন্ট',
        login: 'সফটওয়্যার লগইন',
        nav: {
            home: 'হোম',
            services: 'সেবাসমূহ',
            doctors: 'ডাক্তার',
            facilities: 'সুবিধাসমূহ',
            appointment: 'অ্যাপয়েন্টমেন্ট',
            contact: 'যোগাযোগ',
        },
        heroSub: 'বিশ্বস্ত ডায়াগনস্টিকস, বিশেষজ্ঞ ডাক্তার এবং রোগীকেন্দ্রিক সেবা এক ছাদের নিচে।',
        heroBtn: 'অ্যাপয়েন্টমেন্ট নিন',
        section: {
            home: 'আমাদের সম্পর্কে',
            services: 'আমাদের সেবাসমূহ',
            doctors: 'আমাদের ডাক্তারবৃন্দ',
            facilities: 'সুবিধাসমূহ',
            appointment: 'অনলাইন অ্যাপয়েন্টমেন্ট',
            contact: 'যোগাযোগের তথ্য',
        },
        contactCard: {
            phone: 'ফোন',
            emergency: 'জরুরি',
            address: 'ঠিকানা',
        },
        doctor: {
            specialist: 'বিশেষজ্ঞ ডাক্তার',
        },
        common: {
            na: 'প্রযোজ্য নয়',
        },
        testimonials: 'রোগীর মতামত',
        role: 'বিভাগ',
        form: {
            name: 'রোগীর নাম',
            phone: 'ফোন নম্বর',
            email: 'ইমেইল (ঐচ্ছিক)',
            doctor: 'ডাক্তার নির্বাচন',
            date: 'তারিখ ও সময়',
            message: 'বার্তা',
            submit: 'অ্যাপয়েন্টমেন্ট সাবমিট',
            processing: 'সাবমিট হচ্ছে...',
            placeholderDoctor: 'ডাক্তার নির্বাচন করুন',
            placeholderMessage: 'আপনার সমস্যা লিখুন',
        },
        status: {
            ok: 'অ্যাপয়েন্টমেন্ট সফলভাবে পাঠানো হয়েছে।',
            err: 'ফর্মটি ঠিকভাবে পূরণ করে আবার চেষ্টা করুন।',
        },
    },
};

const t = (path) => {
    const keys = path.split('.');
    let value = copy[currentLang.value];
    for (const key of keys) {
        value = value?.[key];
    }
    return value ?? path;
};

const pickLocalizedText = (value) => {
    const text = String(value ?? '').trim();
    if (!text) {
        return '';
    }

    if (text.includes('||')) {
        const parts = text.split('||', 2).map((item) => item.trim());
        return currentLang.value === 'bn'
            ? (parts[1] || parts[0] || '')
            : (parts[0] || '');
    }

    return text;
};

const hospitalName = computed(() => pickLocalizedText(webSetting.value?.company_name) || 'Toamed Hospital');
const heroTitle = computed(() => pickLocalizedText(webSetting.value?.website_hero_title) || hospitalName.value);
const heroSubtitle = computed(() => pickLocalizedText(webSetting.value?.website_hero_subtitle) || t('heroSub'));
const ctaText = computed(() => pickLocalizedText(webSetting.value?.website_cta_text) || t('heroBtn'));
const aboutText = computed(() => pickLocalizedText(webSetting.value?.website_about_text) || '');
const emergencyPhone = computed(() => pickLocalizedText(webSetting.value?.website_emergency_phone) || webSetting.value?.phone || t('common.na'));

const localizedServiceItems = computed(() => (serviceItems.value || []).map((item) => pickLocalizedText(item)).filter((item) => item !== ''));
const localizedFacilityItems = computed(() => (facilityItems.value || []).map((item) => pickLocalizedText(item)).filter((item) => item !== ''));

const localizedDoctors = computed(() => {
    return (doctors.value || []).map((doctor) => ({
        ...doctor,
        specialty: pickLocalizedText(doctor?.specialty) || t('doctor.specialist'),
        designation: pickLocalizedText(doctor?.designation),
        experience: pickLocalizedText(doctor?.experience),
        bio: pickLocalizedText(doctor?.bio),
    }));
});

const localizedBookingDoctors = computed(() => {
    return (bookingDoctors.value || []).map((doctor) => {
        const specialty = pickLocalizedText(doctor?.specialty);
        const designation = pickLocalizedText(doctor?.designation);
        const experience = pickLocalizedText(doctor?.experience);
        const info = pickLocalizedText(doctor?.info);

        const infoParts = [
            specialty,
            designation,
            experience ? `${currentLang.value === 'bn' ? 'অভিজ্ঞতা' : 'Experience'}: ${experience}` : '',
        ].filter((part) => String(part || '').trim() !== '');

        return {
            ...doctor,
            info: infoParts.length ? infoParts.join(' | ') : info,
        };
    });
});

const switchLanguage = (lang) => {
    if (lang !== 'en' && lang !== 'bn') {
        return;
    }

    currentLang.value = lang;

    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(languageStorageKey, lang);
    window.location.assign(route('backend.language.switch', { locale: lang }));
};

const translateFlashMessage = (message) => {
    const text = String(message ?? '').trim();
    if (!text) {
        return '';
    }

    if (currentLang.value !== 'bn') {
        return text;
    }

    const mappings = {
        'Appointment request submitted successfully.': 'অ্যাপয়েন্টমেন্ট রিকোয়েস্ট সফলভাবে জমা হয়েছে।',
        'Failed to submit appointment request. Please try again.': 'অ্যাপয়েন্টমেন্ট রিকোয়েস্ট জমা হয়নি। আবার চেষ্টা করুন।',
        'Duplicate request detected. Please wait a few minutes before trying again.': 'একই রিকোয়েস্ট বারবার পাওয়া গেছে। কয়েক মিনিট পর আবার চেষ্টা করুন।',
    };

    return mappings[text] || text;
};

const flashSuccessText = computed(() => translateFlashMessage(page.props?.flash?.successMessage));
const flashErrorText = computed(() => translateFlashMessage(page.props?.flash?.errorMessage));

const navLinks = computed(() => ([
    { id: 'home', label: t('nav.home'), href: route('backend.home') },
    { id: 'services', label: t('nav.services'), href: route('backend.website.services') },
    { id: 'doctors', label: t('nav.doctors'), href: route('backend.website.doctors') },
    { id: 'facilities', label: t('nav.facilities'), href: route('backend.website.facilities') },
    { id: 'appointment', label: t('nav.appointment'), href: route('backend.website.appointment') },
    { id: 'contact', label: t('nav.contact'), href: route('backend.website.contact') },
]));

const showSection = (sectionId) => normalizedSection.value === 'home' || normalizedSection.value === sectionId;

const submitAppointment = () => {
    localSuccess.value = '';
    localError.value = '';

    appointmentForm.post(route('backend.website.appointment.store'), {
        preserveScroll: true,
        onSuccess: () => {
            appointmentForm.reset();
            localSuccess.value = t('status.ok');
        },
        onError: () => {
            localError.value = t('status.err');
        },
    });
};

onMounted(() => {
    if (typeof document !== 'undefined') {
        document.documentElement.style.overflowY = 'auto';
        document.body.style.overflowY = 'auto';
    }

    nextTick(() => {
        if (!normalizedSection.value || normalizedSection.value === 'home') {
            return;
        }

        const target = document.getElementById(normalizedSection.value);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

watch(currentLang, (lang) => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(languageStorageKey, lang);
});
</script>

<template>
    <div class="site-wrap">
        <header class="topbar">
            <div class="container topbar-inner">
                <div>{{ t('emergency') }}: <strong>{{ emergencyPhone }}</strong></div>
                <div class="top-actions">
                    <button type="button" class="lang-btn" :class="{ active: currentLang === 'bn' }" @click="switchLanguage('bn')">বাংলা</button>
                    <button type="button" class="lang-btn" :class="{ active: currentLang === 'en' }" @click="switchLanguage('en')">EN</button>
                    <a :href="route('backend.website.appointment')">{{ t('book') }}</a>
                </div>
            </div>
        </header>

        <nav class="navbar">
            <div class="container nav-inner">
                <div class="brand">{{ hospitalName }}</div>
                <div class="links">
                    <a v-for="item in navLinks" :key="item.id" :href="item.href">{{ item.label }}</a>
                </div>
                <a href="/login" class="login">{{ t('login') }}</a>
            </div>
        </nav>

        <section v-if="showSection('home')" id="home" class="hero">
            <div class="container hero-inner">
                <h1>{{ heroTitle }}</h1>
                <p>{{ heroSubtitle }}</p>
                <a :href="route('backend.website.appointment')" class="primary-btn">{{ ctaText }}</a>
            </div>
        </section>

        <main class="container content">
            <section v-if="showSection('home')" class="section-card">
                <h2>{{ t('section.home') }}</h2>
                <p v-if="aboutText" class="muted-about">{{ aboutText }}</p>
            </section>

            <section v-if="showSection('services')" id="services" class="section-card">
                <h2>{{ t('section.services') }}</h2>
                <div class="grid three">
                    <article v-for="(service, i) in localizedServiceItems" :key="`s-${i}`" class="item-card">{{ service }}</article>
                </div>
            </section>

            <section v-if="showSection('doctors')" id="doctors" class="section-card">
                <h2>{{ t('section.doctors') }}</h2>
                <div class="grid three">
                    <article v-for="doctor in localizedDoctors" :key="`${doctor.name}-${doctor.phone}`" class="item-card">
                        <img v-if="doctor.image_url" :src="doctor.image_url" alt="Doctor"
                            class="doctor-image" />
                        <h3>{{ doctor.name }}</h3>
                        <p>{{ doctor.specialty || t('doctor.specialist') }}</p>
                        <p v-if="doctor.phone">{{ doctor.phone }}</p>
                    </article>
                </div>
            </section>

            <section v-if="showSection('facilities')" id="facilities" class="section-card">
                <h2>{{ t('section.facilities') }}</h2>
                <div class="chips">
                    <span v-for="(facility, i) in localizedFacilityItems" :key="`f-${i}`">{{ facility }}</span>
                </div>
            </section>

            <section v-if="showSection('appointment')" id="appointment" class="section-card">
                <h2>{{ t('section.appointment') }}</h2>
                <form class="form-grid" @submit.prevent="submitAppointment">
                    <div>
                        <label>{{ t('form.name') }}</label>
                        <input v-model="appointmentForm.patient_name" type="text" required />
                    </div>
                    <div>
                        <label>{{ t('form.phone') }}</label>
                        <input v-model="appointmentForm.patient_phone" type="text" required />
                    </div>
                    <div>
                        <label>{{ t('form.email') }}</label>
                        <input v-model="appointmentForm.patient_email" type="email" />
                    </div>
                    <div>
                        <label>{{ t('form.doctor') }}</label>
                        <select v-model="appointmentForm.doctor_id" required>
                            <option value="">{{ t('form.placeholderDoctor') }}</option>
                            <option v-for="doctor in localizedBookingDoctors" :key="doctor.id" :value="doctor.id">{{ doctor.name }}{{ doctor.info ? ` - ${doctor.info}` : '' }}</option>
                        </select>
                    </div>
                    <div>
                        <label>{{ t('form.date') }}</label>
                        <input v-model="appointmentForm.appointment_date" type="datetime-local" required />
                    </div>
                    <div class="full">
                        <label>{{ t('form.message') }}</label>
                        <textarea v-model="appointmentForm.message" rows="3" :placeholder="t('form.placeholderMessage')"></textarea>
                    </div>
                    <input v-model="appointmentForm.website_url" type="text" class="honeypot" tabindex="-1" aria-hidden="true" />
                    <div class="full actions">
                        <button class="primary-btn" type="submit" :disabled="appointmentForm.processing">
                            {{ appointmentForm.processing ? t('form.processing') : t('form.submit') }}
                        </button>
                        <p v-if="localSuccess" class="ok">{{ localSuccess }}</p>
                        <p v-if="localError" class="err">{{ localError }}</p>
                        <p v-if="flashSuccessText" class="ok">{{ flashSuccessText }}</p>
                        <p v-if="flashErrorText" class="err">{{ flashErrorText }}</p>
                    </div>
                </form>
            </section>

            <section v-if="showSection('contact')" id="contact" class="section-card">
                <h2>{{ t('section.contact') }}</h2>
                <div class="grid three">
                    <article class="item-card">
                        <h3>{{ t('contactCard.phone') }}</h3>
                        <p>{{ pickLocalizedText(webSetting?.phone) || t('common.na') }}</p>
                    </article>
                    <article class="item-card">
                        <h3>{{ t('contactCard.emergency') }}</h3>
                        <p>{{ emergencyPhone }}</p>
                    </article>
                    <article class="item-card">
                        <h3>{{ t('contactCard.address') }}</h3>
                        <p>{{ pickLocalizedText(webSetting?.address) || t('common.na') }}</p>
                    </article>
                </div>
            </section>

            <section v-if="showSection('home')" class="section-card">
                <h2>{{ t('testimonials') }}</h2>
                <div class="grid three">
                    <article class="item-card" v-for="(item, i) in ($page.props.testimonialItems?.[currentLang] || $page.props.testimonialItems?.en || [])" :key="`testimonial-${i}`">
                        <p style="margin: 0 0 10px 0; color: #334155; white-space: pre-wrap;">"{{ item.quote || '' }}"</p>
                        <p style="margin: 0; font-weight: 700;">{{ item.name || '' }}</p>
                        <p class="muted-about" style="margin-top: 4px;">{{ t('role') }}: {{ item.role || '' }}</p>
                    </article>
                </div>
            </section>
        </main>
    </div>
</template>

<style scoped>
:global(html) { scroll-behavior: smooth; }
:global(body) {
    margin: 0;
    font-family: "Lato", "Segoe UI", sans-serif;
    background: #f6f8fb;
    color: #12314a;
    overflow-y: auto;
}

:global(html) {
    overflow-y: auto;
}

.site-wrap { min-height: 100vh; }
.container { width: min(1140px, calc(100% - 28px)); margin-inline: auto; }
.muted-about { margin: 0 0 14px 0; color: #475569; white-space: pre-wrap; }

.topbar {
    background: #0d4a78;
    color: #eaf3fb;
    font-size: 13px;
}

.topbar-inner {
    min-height: 42px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.top-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.top-actions a {
    color: inherit;
    text-decoration: none;
    font-weight: 700;
}

.lang-btn {
    border: 1px solid rgba(255,255,255,0.45);
    border-radius: 999px;
    background: transparent;
    color: #fff;
    padding: 3px 8px;
    font-size: 11px;
    cursor: pointer;
}

.lang-btn.active { background: #fff; color: #0d4a78; }

.navbar {
    background: #fff;
    border-bottom: 1px solid #d9e6f1;
    position: sticky;
    top: 0;
    z-index: 20;
}

.nav-inner {
    min-height: 64px;
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 14px;
    align-items: center;
}

.brand {
    font-size: 22px;
    font-weight: 800;
    color: #0d4a78;
}

.links {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    justify-content: center;
}

.links a {
    color: #1e4e71;
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
}

.login {
    color: #0d4a78;
    text-decoration: none;
    font-weight: 700;
}

.hero {
    background: linear-gradient(135deg, #0e507f, #1478a6);
    color: #fff;
    padding: 56px 0;
}

.hero-inner { max-width: 760px; }
.hero h1 {
    margin: 0 0 10px;
    font-size: clamp(30px, 4.5vw, 50px);
    line-height: 1.1;
}
.hero p {
    margin: 0;
    color: #dbeaf7;
}

.primary-btn {
    margin-top: 18px;
    display: inline-block;
    border: 0;
    border-radius: 999px;
    background: #1ad3b5;
    color: #ffffff;
    font-weight: 800;
    padding: 11px 18px;
    text-decoration: none;
    cursor: pointer;
}

.content {
    padding: 22px 0 60px;
    display: grid;
    gap: 16px;
}

.section-card {
    background: #fff;
    border: 1px solid #dce8f2;
    border-radius: 14px;
    padding: 16px;
}

.section-card h2 {
    margin: 0 0 12px;
    color: #0d4a78;
    font-size: 24px;
}

.grid {
    display: grid;
    gap: 12px;
}

.grid.three { grid-template-columns: repeat(3, minmax(0, 1fr)); }

.item-card {
    background: #f8fcff;
    border: 1px solid #d8e9f5;
    border-radius: 12px;
    padding: 12px;
}

.item-card h3 {
    margin: 0 0 6px;
    color: #0d4a78;
}

.item-card p { margin: 0; color: #355770; }

.doctor-image {
    width: 100%;
    height: 170px;
    object-fit: cover;
    border-radius: 10px;
    margin-bottom: 8px;
    border: 1px solid #d8e9f5;
}

.chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.chips span {
    background: #e8f4fc;
    border: 1px solid #cfe5f4;
    border-radius: 999px;
    padding: 8px 12px;
    font-size: 13px;
    font-weight: 700;
    color: #0f4f7d;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

label {
    display: block;
    margin-bottom: 6px;
    font-size: 13px;
    font-weight: 700;
}

input,
select,
textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #cde0ef;
    border-radius: 10px;
    padding: 9px 11px;
    font-family: inherit;
}

.full { grid-column: 1 / -1; }
.actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.honeypot {
    position: absolute;
    left: -9999px;
    top: -9999px;
    opacity: 0;
}

.ok { color: #0c9461; font-weight: 700; }
.err { color: #d52525; font-weight: 700; }

@media (max-width: 900px) {
    .nav-inner { grid-template-columns: 1fr; padding: 10px 0; }
    .links { justify-content: flex-start; }
    .grid.three,
    .form-grid { grid-template-columns: 1fr; }
}
</style>
