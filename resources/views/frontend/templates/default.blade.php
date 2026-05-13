<!doctype html>
<html lang="{{ app()->getLocale() }}">
@php
    $setting = $webSetting ?? (function_exists('get_cached_web_setting') ? get_cached_web_setting() : null);
    $locale = app()->getLocale();
    if (!in_array($locale, ['en', 'bn'], true)) {
        $locale = 'en';
    }

    $isBn = $locale === 'bn';
    $t = [
        'emergency' => $isBn ? 'জরুরি' : 'Emergency',
        'language' => $isBn ? 'ভাষা' : 'Language',
        'english' => 'English',
        'bangla' => 'বাংলা',
        'home' => $isBn ? 'হোম' : 'Home',
        'services' => $isBn ? 'সার্ভিস' : 'Services',
        'doctors' => $isBn ? 'ডাক্তার' : 'Doctors',
        'facilities' => $isBn ? 'সুবিধা' : 'Facilities',
        'contact' => $isBn ? 'যোগাযোগ' : 'Contact',
        'software_login' => $isBn ? 'সফটওয়্যার লগইন' : 'Software Login',
        'book_appointment' => $isBn ? 'অ্যাপয়েন্টমেন্ট করুন' : 'Book Appointment',
        'about' => $isBn ? 'আমাদের সম্পর্কে' : 'About',
        'appointment_section' => $isBn ? 'অ্যাপয়েন্টমেন্ট' : 'Appointment',
        'appointment_note' => $isBn ? 'অ্যাপয়েন্টমেন্ট করতে নিচের বাটনে ক্লিক করুন অথবা জরুরি নম্বরে যোগাযোগ করুন।' : 'To book an appointment, use the button below or call the emergency number.',
        'appointment_form_title' => $isBn ? 'অনলাইন অ্যাপয়েন্টমেন্ট ফর্ম' : 'Online Appointment Form',
        'patient_name' => $isBn ? 'রোগীর নাম' : 'Patient Name',
        'phone' => $isBn ? 'ফোন নম্বর' : 'Phone Number',
        'email_optional' => $isBn ? 'ইমেইল (ঐচ্ছিক)' : 'Email (Optional)',
        'select_doctor' => $isBn ? 'ডাক্তার নির্বাচন করুন' : 'Select Doctor',
        'appointment_datetime' => $isBn ? 'তারিখ ও সময়' : 'Appointment Date & Time',
        'message' => $isBn ? 'বার্তা' : 'Message',
        'message_placeholder' => $isBn ? 'আপনার সমস্যা সংক্ষেপে লিখুন' : 'Write your concern briefly',
        'submit_appointment' => $isBn ? 'অ্যাপয়েন্টমেন্ট সাবমিট করুন' : 'Submit Appointment',
        'doctor_unavailable' => $isBn ? 'এই মুহূর্তে কোনো ডাক্তার অ্যাপয়েন্টমেন্টের জন্য পাওয়া যাচ্ছে না।' : 'No doctors are currently available for appointment.',
        'choose_doctor' => $isBn ? 'ডাক্তার নির্বাচন করুন' : 'Choose Doctor',
        'featured_doctors' => $isBn ? 'বিশেষজ্ঞ ডাক্তার' : 'Featured Doctors',
        'testimonials' => $isBn ? 'রোগীর মতামত' : 'Testimonials',
        'role' => $isBn ? 'বিভাগ' : 'Role',
        'no_services' => $isBn ? 'কোনো সার্ভিস কনফিগার করা হয়নি।' : 'No services configured.',
        'no_facilities' => $isBn ? 'কোনো ফ্যাসিলিটি কনফিগার করা হয়নি।' : 'No facilities configured.',
        'no_doctors' => $isBn ? 'কোনো ডাক্তার কনফিগার করা হয়নি।' : 'No doctors configured.',
        'no_testimonials' => $isBn ? 'কোনো মতামত কনফিগার করা হয়নি।' : 'No testimonials configured.',
        'contact_label' => $isBn ? 'যোগাযোগ' : 'Contact',
    ];

    $bnFallbackMap = [
        'Welcome to Toamed Hospital' => 'টোআমেড হাসপাতালে স্বাগতম',
        'Welcome to Toamed ospital' => 'টোআমেড হাসপাতালে স্বাগতম',
        'Compassionate care, trusted doctors, and modern hospital services for your family.' => 'সহানুভূতিশীল সেবা, বিশ্বস্ত ডাক্তার এবং আপনার পরিবারের জন্য আধুনিক হাসপাতাল সুবিধা।',
        'Book an Appointment Today' => 'আজই অ্যাপয়েন্টমেন্ট নিন',
        'Toamed Hospital provides specialist consultation, diagnostics, emergency support, and day-to-day healthcare under one roof.' => 'টোআমেড হাসপাতাল এক ছাদের নিচে বিশেষজ্ঞ পরামর্শ, ডায়াগনস্টিকস, জরুরি সাপোর্ট এবং দৈনন্দিন স্বাস্থ্যসেবা প্রদান করে।',
        'Emergency & Trauma' => 'ইমার্জেন্সি ও ট্রমা',
        'Diagnostics' => 'ডায়াগনস্টিকস',
        'Specialist Consultation' => 'বিশেষজ্ঞ পরামর্শ',
        'Digital Queue & Token' => 'ডিজিটাল টোকেন সিস্টেম',
        'In-house Pharmacy' => 'ইন-হাউস ফার্মেসি',
        'Cashless Billing Ready' => 'ক্যাশলেস বিলিং সুবিধা',
        'Online Report Delivery' => 'অনলাইন রিপোর্ট ডেলিভারি',
        'Dedicated Help Desk' => 'ডেডিকেটেড হেল্প ডেস্ক',
        'Medicine' => 'মেডিসিন',
        'Cardiology' => 'কার্ডিওলজি',
        'Consultant Doctor' => 'কনসালট্যান্ট ডাক্তার',
        'Specialist Doctor' => 'বিশেষজ্ঞ ডাক্তার',
        'No doctors configured' => 'কোনো ডাক্তার কনফিগার করা হয়নি',
        'Cardiac Care' => 'কার্ডিয়াক কেয়ার',
        'Emergency Support' => 'ইমার্জেন্সি সাপোর্ট',
        'Diagnostic Service' => 'ডায়াগনস্টিক সার্ভিস',
        'MBBS, FCPS' => 'এমবিবিএস, এফসিপিএস',
    ];

    $autoTranslateBn = function (string $input) use ($bnFallbackMap) {
        $trimmed = trim($input);
        if ($trimmed === '') {
            return '';
        }

        if (array_key_exists($trimmed, $bnFallbackMap)) {
            return $bnFallbackMap[$trimmed];
        }

        $lines = preg_split('/\R/', $input);
        if (!is_array($lines) || count($lines) <= 1) {
            return $input;
        }

        $translated = array_map(function ($line) use ($bnFallbackMap) {
            $lineTrimmed = trim((string) $line);
            if ($lineTrimmed === '') {
                return (string) $line;
            }

            return $bnFallbackMap[$lineTrimmed] ?? (string) $line;
        }, $lines);

        return implode("\n", $translated);
    };

    $pickLocalizedText = function ($value) use ($locale, $autoTranslateBn) {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        // Support lightweight bilingual format: "English text || বাংলা টেক্সট"
        if (str_contains($text, '||')) {
            $parts = array_map('trim', explode('||', $text, 2));
            if ($locale === 'bn') {
                return $parts[1] ?? $parts[0] ?? '';
            }

            return $parts[0] ?? '';
        }

        if ($locale === 'bn') {
            return $autoTranslateBn($text);
        }

        return $text;
    };

    $serviceList = $serviceItems ?? [];
    if (empty($serviceList) && !empty($setting?->website_services_json)) {
        $parsed = json_decode((string) $setting->website_services_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            $serviceList = $parsed;
        }
    }

    $facilityList = $facilityItems ?? [];
    if (empty($facilityList) && !empty($setting?->website_facilities_json)) {
        $parsed = json_decode((string) $setting->website_facilities_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            $facilityList = $parsed;
        }
    }

    $doctorsList = $featuredDoctors ?? [];
    if (empty($doctorsList) && !empty($setting?->website_featured_doctors_json)) {
        $parsed = json_decode((string) $setting->website_featured_doctors_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            $doctorsList = $parsed;
        }
    }

    $localizeListLabel = function ($item) use ($locale, $pickLocalizedText) {
        if (is_array($item)) {
            if ($locale === 'bn') {
                $candidate = $item['label_bn'] ?? $item['bn'] ?? $item['label'] ?? '';
                return $pickLocalizedText($candidate);
            }

            $candidate = $item['label_en'] ?? $item['en'] ?? $item['label'] ?? '';
            return $pickLocalizedText($candidate);
        }

        return $pickLocalizedText($item);
    };

    $serviceList = array_values(array_filter(array_map($localizeListLabel, (array) $serviceList), fn ($item) => $item !== ''));
    $facilityList = array_values(array_filter(array_map($localizeListLabel, (array) $facilityList), fn ($item) => $item !== ''));

    $heroTitle = $pickLocalizedText($setting->website_hero_title ?? ($setting->company_name ?? 'Welcome'));
    $heroSubtitle = $pickLocalizedText($setting->website_hero_subtitle ?? '');
    $aboutText = $pickLocalizedText($setting->website_about_text ?? '');
    $ctaText = $pickLocalizedText($setting->website_cta_text ?? '');
    $initialSection = trim((string) ($initialSection ?? 'home'));
    $initialSection = $initialSection !== '' ? $initialSection : 'home';
    $allowedSections = ['home', 'services', 'doctors', 'facilities', 'appointment', 'contact'];
    $initialSection = in_array($initialSection, $allowedSections, true) ? $initialSection : 'home';
    $showSection = fn (string $section): bool => $initialSection === 'home' || $initialSection === $section;

    $testimonialList = [];
    if (!empty($testimonialItems) && is_array($testimonialItems)) {
        $testimonialList = $testimonialItems[$locale] ?? $testimonialItems['en'] ?? [];
    }
@endphp
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $setting->company_name ?? config('app.name', 'Hospital') }}</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; color: #0f172a; background: #f8fafc; }
        .container { width: min(1100px, calc(100% - 24px)); margin: 0 auto; }
        .topbar { background: #0f3b66; color: #e2e8f0; padding: 10px 0; font-size: 13px; }
        .topbar .inner { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .topbar-tools { display: inline-flex; align-items: center; gap: 10px; }
        .topbar-lang { display: inline-flex; align-items: center; gap: 6px; }
        .topbar-lang .lang-link { color: #e2e8f0; text-decoration: none; padding: 2px 6px; border-radius: 999px; border: 1px solid transparent; }
        .topbar-lang .lang-link:hover { background: rgba(255, 255, 255, 0.16); }
        .topbar-lang .lang-link.is-active { border-color: rgba(255, 255, 255, 0.35); background: rgba(255, 255, 255, 0.18); }
        .topbar-login { color: #ffffff; text-decoration: none; font-weight: 700; padding: 2px 8px; border-radius: 999px; border: 1px solid rgba(255, 255, 255, 0.35); }
        .topbar-login:hover { background: rgba(255, 255, 255, 0.16); }
        .nav { background: #ffffff; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 30; }
        .nav .inner { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; }
        .brand { display: flex; gap: 10px; align-items: center; }
        .brand img { width: 44px; height: 44px; object-fit: contain; border-radius: 6px; }
        .nav-right { display: flex; align-items: center; gap: 14px; }
        .nav-links { display: flex; align-items: center; gap: 12px; }
        .nav-link { color: #1e293b; text-decoration: none; font-size: 14px; }
        .nav-link:hover { color: #0284c7; }
        .nav-tools { display: flex; align-items: center; gap: 10px; }
        .hero { background: linear-gradient(120deg, #e0f2fe, #f1f5f9); padding: 48px 0; }
        .hero h1 { margin: 0 0 8px 0; font-size: 34px; }
        .hero p { margin: 0 0 14px 0; color: #334155; }
        html { scroll-behavior: smooth; }
        .btn { display: inline-block; background: #0284c7; color: #fff; text-decoration: none; padding: 10px 14px; border-radius: 8px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .form-grid .full { grid-column: 1 / -1; }
        .field-label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; color: #1e293b; }
        .field-input { width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px; font-size: 14px; }
        .alert-ok { background: #dcfce7; color: #166534; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
        .alert-err { background: #fee2e2; color: #991b1b; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
        .muted-sm { font-size: 13px; color: #64748b; }
        .section { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin: 16px 0; }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; background: #fff; }
        .doctor-photo { width: 100%; height: 180px; object-fit: cover; border-radius: 6px; background: #eef2f7; }
        .muted { color: #64748b; }
        @media (max-width: 900px) {
            .topbar .inner { flex-direction: column; align-items: flex-start; }
            .topbar-tools { width: 100%; justify-content: space-between; }
            .nav .inner { flex-direction: column; align-items: flex-start; gap: 10px; }
            .nav-right { width: 100%; justify-content: space-between; }
            .nav-links { flex-wrap: wrap; gap: 10px; }
            .nav-tools { width: 100%; justify-content: space-between; }
            .grid { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .hero h1 { font-size: 26px; }
        }
    </style>
</head>
<body data-initial-section="{{ $initialSection }}">
    <div class="topbar">
        <div class="container inner">
            <div>{{ $t['emergency'] }}: <strong>{{ $setting->website_emergency_phone ?? $setting->phone ?? 'N/A' }}</strong></div>
            <div class="topbar-tools">
                <div class="topbar-lang">
                    <span>{{ $t['language'] }}:</span>
                    <a class="lang-link {{ $locale === 'en' ? 'is-active' : '' }}" href="{{ url('/language/en') }}">{{ $t['english'] }}</a>
                    <span>/</span>
                    <a class="lang-link {{ $locale === 'bn' ? 'is-active' : '' }}" href="{{ url('/language/bn') }}">{{ $t['bangla'] }}</a>
                </div>
                <a class="topbar-login" href="{{ url('/login') }}">{{ $t['software_login'] }}</a>
            </div>
        </div>
    </div>

    <div class="nav">
        <div class="container inner">
            <div class="brand">
                @if(!empty($setting?->logo))
                    <img src="{{ $setting->logo }}" alt="logo">
                @endif
                <div>
                    <div><strong>{{ $setting->company_name ?? 'Hospital' }}</strong></div>
                    <div class="muted" style="font-size: 12px;">{{ $setting->address ?? '' }}</div>
                </div>
            </div>

            <div class="nav-right">
                <div class="nav-links">
                    <a class="nav-link" href="{{ url('/') }}">{{ $t['home'] }}</a>
                    <a class="nav-link" href="{{ url('/services') }}">{{ $t['services'] }}</a>
                    <a class="nav-link" href="{{ url('/doctors') }}">{{ $t['doctors'] }}</a>
                    <a class="nav-link" href="{{ url('/facilities') }}">{{ $t['facilities'] }}</a>
                    <a class="nav-link" href="{{ url('/contact') }}">{{ $t['contact'] }}</a>
                </div>
                <div class="nav-tools">
                    <a class="btn" href="{{ route('backend.website.appointment') }}">{{ $ctaText !== '' ? $ctaText : $t['book_appointment'] }}</a>
                </div>
            </div>
        </div>
    </div>

    @if($showSection('home'))
    <section id="home" class="hero">
        <div class="container">
            <h1>{{ $heroTitle }}</h1>
            <p>{{ $heroSubtitle }}</p>
            <a class="btn" href="{{ route('backend.website.appointment') }}">{{ $ctaText !== '' ? $ctaText : $t['book_appointment'] }}</a>
        </div>
    </section>
    @endif

    <div class="container">
        @if($showSection('home'))
        <section class="section">
            <h2>{{ $t['about'] }}</h2>
            <p style="white-space: pre-wrap;">{{ $aboutText }}</p>
        </section>
        @endif

        @if($showSection('services'))
        <section id="services" class="section">
            <h2>{{ $t['services'] }}</h2>
            <div class="grid">
                @forelse($serviceList as $item)
                    <div class="card">{{ is_string($item) ? $item : ($item['label'] ?? '') }}</div>
                @empty
                    <div class="muted">{{ $t['no_services'] }}</div>
                @endforelse
            </div>
        </section>
        @endif

        @if($showSection('facilities'))
        <section id="facilities" class="section">
            <h2>{{ $t['facilities'] }}</h2>
            <div class="grid">
                @forelse($facilityList as $item)
                    <div class="card">{{ is_string($item) ? $item : ($item['label'] ?? '') }}</div>
                @empty
                    <div class="muted">{{ $t['no_facilities'] }}</div>
                @endforelse
            </div>
        </section>
        @endif

        @if($showSection('doctors'))
        <section id="doctors" class="section">
            <h2>{{ $t['featured_doctors'] }}</h2>
            <div class="grid">
                @forelse($doctorsList as $doc)
                    @php
                        $rawImg = trim((string) ($doc['image_url'] ?? ''));
                        $imgSrc = null;
                        if ($rawImg !== '') {
                            $imgSrc = publicStorageUrl($rawImg) ?: null;
                        }
                    @endphp
                    <div class="card">
                        @if($imgSrc)
                            <img class="doctor-photo" src="{{ $imgSrc }}" alt="{{ $doc['name'] ?? '' }}">
                        @endif
                        <h3 style="margin: 10px 0 6px 0;">{{ $doc['name'] ?? '' }}</h3>
                        <div class="muted" style="white-space: pre-wrap;">{{ $pickLocalizedText($doc['specialty'] ?? '') }}</div>
                        <div style="margin-top: 4px;">{{ $pickLocalizedText($doc['designation'] ?? '') }}</div>
                        @if(!empty($doc['phone']))
                            <div class="muted" style="margin-top: 4px;">{{ $doc['phone'] }}</div>
                        @endif
                    </div>
                @empty
                    <div class="muted">{{ $t['no_doctors'] }}</div>
                @endforelse
            </div>
        </section>
        @endif

        @if($showSection('home'))
        <section class="section">
            <h2>{{ $t['testimonials'] }}</h2>
            <div class="grid">
                @forelse($testimonialList as $item)
                    <div class="card">
                        <p style="margin: 0 0 10px 0; color: #334155; white-space: pre-wrap;">"{{ $item['quote'] ?? '' }}"</p>
                        <p style="margin: 0; font-weight: 700;">{{ $item['name'] ?? '' }}</p>
                        <p class="muted" style="margin: 4px 0 0 0;">{{ $t['role'] }}: {{ $item['role'] ?? '' }}</p>
                    </div>
                @empty
                    <div class="muted">{{ $t['no_testimonials'] }}</div>
                @endforelse
            </div>
        </section>
        @endif

        @if($showSection('appointment'))
        <section id="appointment" class="section">
            <h2>{{ $t['appointment_section'] }}</h2>
            <p class="muted">{{ $t['appointment_note'] }}</p>

            @if (session('successMessage'))
                <div class="alert-ok">{{ session('successMessage') }}</div>
            @endif

            @if (session('errorMessage'))
                <div class="alert-err">{{ session('errorMessage') }}</div>
            @endif

            <h3 style="margin: 12px 0 10px 0;">{{ $t['appointment_form_title'] }}</h3>

            @if(!empty($bookingDoctors) && count($bookingDoctors) > 0)
                <form method="POST" action="{{ route('backend.website.appointment.store') }}" class="form-grid">
                    @csrf

                    <div>
                        <label class="field-label" for="patient_name">{{ $t['patient_name'] }}</label>
                        <input class="field-input" id="patient_name" name="patient_name" type="text" value="{{ old('patient_name') }}" required>
                    </div>

                    <div>
                        <label class="field-label" for="patient_phone">{{ $t['phone'] }}</label>
                        <input class="field-input" id="patient_phone" name="patient_phone" type="text" value="{{ old('patient_phone') }}" required>
                    </div>

                    <div>
                        <label class="field-label" for="patient_email">{{ $t['email_optional'] }}</label>
                        <input class="field-input" id="patient_email" name="patient_email" type="email" value="{{ old('patient_email') }}">
                    </div>

                    <div>
                        <label class="field-label" for="doctor_id">{{ $t['select_doctor'] }}</label>
                        <select class="field-input" id="doctor_id" name="doctor_id" required>
                            <option value="">{{ $t['choose_doctor'] }}</option>
                            @foreach($bookingDoctors as $doctor)
                                <option value="{{ $doctor['id'] }}" @selected((string) old('doctor_id') === (string) $doctor['id'])>
                                    {{ $doctor['name'] }}{{ !empty($doctor['info']) ? ' - ' . $doctor['info'] : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="field-label" for="appointment_date">{{ $t['appointment_datetime'] }}</label>
                        <input class="field-input" id="appointment_date" name="appointment_date" type="datetime-local" value="{{ old('appointment_date') }}" required>
                    </div>

                    <div class="full">
                        <label class="field-label" for="message">{{ $t['message'] }}</label>
                        <textarea class="field-input" id="message" name="message" rows="3" placeholder="{{ $t['message_placeholder'] }}">{{ old('message') }}</textarea>
                    </div>

                    <input type="text" name="website_url" style="display:none" tabindex="-1" autocomplete="off">

                    <div class="full" style="display:flex; align-items:center; gap:10px; flex-wrap: wrap;">
                        <button class="btn" type="submit">{{ $t['submit_appointment'] }}</button>
                    </div>
                </form>
            @else
                <p class="muted-sm">{{ $t['doctor_unavailable'] }}</p>
            @endif
        </section>
        @endif

        @if($showSection('contact'))
        <section id="contact" class="section" style="margin-bottom: 28px;">
            <strong>{{ $t['contact_label'] }}:</strong> {{ $setting->website_emergency_phone ?? $setting->phone ?? 'N/A' }}
        </section>
        @endif
    </div>

    <script>
        (function () {
            var initialSection = document.body ? (document.body.getAttribute('data-initial-section') || 'home') : 'home';
            if (!initialSection || initialSection === 'home') {
                return;
            }

            window.addEventListener('load', function () {
                var target = document.getElementById(initialSection);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        })();
    </script>
</body>
</html>
