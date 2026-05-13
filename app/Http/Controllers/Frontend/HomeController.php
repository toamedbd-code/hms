<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Appoinment;
use App\Models\WebSetting;
use Illuminate\Support\Facades\Log;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

class HomeController extends Controller
{

    public function __construct()
    {
      
    }

    public function index()
    {
        return $this->renderWebsite('home');
    }

    public function services()
    {
        return $this->renderWebsite('services');
    }

    public function doctors()
    {
        return $this->renderWebsite('doctors');
    }

    public function facilities()
    {
        return $this->renderWebsite('facilities');
    }

    public function appointment()
    {
        return $this->renderWebsite('appointment');
    }

    public function contact()
    {
        return $this->renderWebsite('contact');
    }

    private function renderWebsite(string $initialSection)
    {
        $webSetting = get_cached_web_setting();

        if ($redirect = $this->redirectIfWebsiteDisabled($webSetting)) {
            return $redirect;
        }

        $locale = strtolower((string) app()->getLocale());
        if (!in_array($locale, ['en', 'bn'], true)) {
            $locale = 'en';
        }

        $text = [
            'consultant_doctor' => $locale === 'bn' ? 'কনসালট্যান্ট ডাক্তার' : 'Consultant Doctor',
            'specialist_doctor' => $locale === 'bn' ? 'বিশেষজ্ঞ ডাক্তার' : 'Specialist Doctor',
            'no_doctors_configured' => $locale === 'bn' ? 'কোনো ডাক্তার কনফিগার করা হয়নি' : 'No doctors configured',
            'experience_label' => $locale === 'bn' ? 'অভিজ্ঞতা' : 'Experience',
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
        ];

        $autoTranslateBn = function (string $input) use ($bnFallbackMap): string {
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
            $textValue = trim((string) $value);
            if ($textValue === '') {
                return '';
            }

            if (str_contains($textValue, '||')) {
                $parts = array_map('trim', explode('||', $textValue, 2));
                if ($locale === 'bn') {
                    return $parts[1] ?? $parts[0] ?? '';
                }

                return $parts[0] ?? '';
            }

            if ($locale === 'bn') {
                return $autoTranslateBn($textValue);
            }

            return $textValue;
        };

        $bookingDoctors = Admin::query()
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhereRaw('LOWER(status) = ?', ['active']);
            })
            ->where(function ($query) {
                $query->whereHas('role', function ($roleQuery) {
                    $roleQuery->whereRaw('LOWER(TRIM(name)) IN (?, ?)', ['doctor', 'doctors']);
                })->orWhereHas('roles', function ($roleQuery) {
                    $roleQuery->whereRaw('LOWER(TRIM(name)) IN (?, ?)', ['doctor', 'doctors']);
                });
            })
            ->get(['id', 'first_name', 'last_name', 'phone'])
            ->map(function ($doctor) use ($text) {
                return [
                    'id' => $doctor->id,
                    'name' => trim(($doctor->first_name ?? '') . ' ' . ($doctor->last_name ?? '')),
                    'phone' => $doctor->phone,
                    'specialty' => $text['consultant_doctor'],
                    'designation' => '',
                    'experience' => '',
                    'info' => $text['consultant_doctor'],
                ];
            })
            ->values();

        $featuredDoctors = [];
        $rawFeaturedDoctors = trim((string) ($webSetting?->website_featured_doctors_json ?? ''));

        if ($rawFeaturedDoctors !== '') {
            $decoded = json_decode($rawFeaturedDoctors, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $featuredDoctors = collect($decoded)
                    ->filter(fn ($item) => is_array($item) && !empty($item['name']))
                    ->map(function ($item) use ($pickLocalizedText, $text) {
                        return [
                            'name' => (string) ($item['name'] ?? ''),
                            'specialty' => $pickLocalizedText((string) ($item['specialty'] ?? $text['specialist_doctor'])),
                            'designation' => $pickLocalizedText((string) ($item['designation'] ?? '')),
                            'phone' => (string) ($item['phone'] ?? ''),
                            'experience' => $pickLocalizedText((string) ($item['experience'] ?? '')),
                            'bio' => $pickLocalizedText((string) ($item['bio'] ?? '')),
                            'image_url' => (string) ($item['image_url'] ?? ''),
                        ];
                    })
                    ->values()
                    ->toArray();
            }
        }

        // Merge booking doctors (admins) into featured list so both CMS and admin-created doctors appear on website.
        $mergedFeatured = collect($featuredDoctors);

        // Build a set of identifiers to avoid duplicates (prefer phone if available, fallback to name)
        $existingKeys = $mergedFeatured->map(function ($d) {
            return trim((string) ($d['phone'] ?? $d['name'] ?? ''));
        })->filter()->values()->all();

        $bookingDoctors->each(function ($doctor) use (&$mergedFeatured, $existingKeys, $text) {
            $key = trim((string) ($doctor['phone'] ?? $doctor['name'] ?? ''));
            if ($key === '') return;
            if (!in_array($key, $existingKeys, true)) {
                $mergedFeatured->push([
                    'name' => $doctor['name'],
                    'specialty' => $text['consultant_doctor'],
                    'designation' => '',
                    'phone' => $doctor['phone'] ?? '',
                    'experience' => '',
                    'bio' => '',
                    'image_url' => '',
                ]);
            }
        });

        if ($mergedFeatured->isEmpty()) {
            // Fallback to a minimal mock if nothing exists
            $mergedFeatured = collect([
                ['name' => $text['no_doctors_configured'], 'specialty' => '', 'phone' => '', 'image_url' => '']
            ]);
        }

        $featuredDoctors = $mergedFeatured->values()->toArray();

        // Ensure bookingDoctors also includes admins referenced by featured doctors (CMS)
        $decodedFeatured = [];
        if ($rawFeaturedDoctors !== '') {
            $tmp = json_decode($rawFeaturedDoctors, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
                $decodedFeatured = $tmp;
            }
        }

        $extraAdminIds = [];
        if (!empty($decodedFeatured) && is_array($decodedFeatured)) {
            foreach ($decodedFeatured as $item) {
                if (!is_array($item)) continue;
                if (!empty($item['admin_id'])) {
                    $extraAdminIds[] = (int) $item['admin_id'];
                } else {
                    // try to match existing admin by phone/email/name
                    $phone = trim((string) ($item['phone'] ?? ''));
                    $email = trim((string) ($item['email'] ?? ''));
                    $name = trim((string) ($item['name'] ?? ''));
                    $found = null;
                    if ($phone !== '') {
                        $found = \App\Models\Admin::where('phone', $phone)->first();
                    }
                    if (!$found && $email !== '') {
                        $found = \App\Models\Admin::where('email', $email)->first();
                    }
                    if (!$found && $name !== '') {
                        $parts = preg_split('/\s+/', $name, 2);
                        $first = $parts[0] ?? $name;
                        $last = $parts[1] ?? '';
                        $found = \App\Models\Admin::where('first_name', $first)->where('last_name', $last)->first();
                    }
                    if ($found) {
                        $extraAdminIds[] = (int) $found->id;
                    }
                }
            }
        }

        if (!empty($extraAdminIds)) {
            $extraAdminIds = array_values(array_unique(array_filter($extraAdminIds)));
            if (!empty($extraAdminIds)) {
                $additional = \App\Models\Admin::query()
                    ->whereIn('id', $extraAdminIds)
                    ->whereNull('deleted_at')
                    ->where(function ($query) {
                        $query->whereNull('status')
                            ->orWhereRaw('LOWER(status) = ?', ['active']);
                    })
                    ->get(['id', 'first_name', 'last_name', 'phone'])
                    ->map(function ($doctor) use ($text) {
                        return [
                            'id' => $doctor->id,
                            'name' => trim(($doctor->first_name ?? '') . ' ' . ($doctor->last_name ?? '')),
                            'phone' => $doctor->phone,
                            'specialty' => $text['consultant_doctor'],
                            'designation' => '',
                            'experience' => '',
                            'info' => $text['consultant_doctor'],
                        ];
                    })
                    ->values();

                // Merge unique by id
                $bookingById = $bookingDoctors->keyBy('id');
                foreach ($additional as $ad) {
                    if (!$bookingById->has($ad['id'])) {
                        $bookingById->put($ad['id'], $ad);
                    }
                }

                $bookingDoctors = $bookingById->values();
            }
        }

        // Enrich booking doctor dropdown labels with specialty/designation data
        // from CMS doctor entries so we can avoid showing phone numbers.
        $featuredMetaByAdminId = [];
        $featuredMetaByPhone = [];
        $featuredMetaByName = [];

        foreach ($decodedFeatured as $item) {
            if (!is_array($item)) {
                continue;
            }

            $specialty = $pickLocalizedText((string) ($item['specialty'] ?? ''));
            $designation = $pickLocalizedText((string) ($item['designation'] ?? ''));
            $experience = $pickLocalizedText((string) ($item['experience'] ?? ''));

            if ($specialty === '') {
                $specialty = $text['consultant_doctor'];
            }

            $meta = [
                'specialty' => $specialty,
                'designation' => $designation,
                'experience' => $experience,
            ];

            $adminId = isset($item['admin_id']) ? (int) $item['admin_id'] : 0;
            if ($adminId > 0) {
                $featuredMetaByAdminId[$adminId] = $meta;
            }

            $phoneKey = trim((string) ($item['phone'] ?? ''));
            if ($phoneKey !== '') {
                $featuredMetaByPhone[$phoneKey] = $meta;
            }

            $nameKey = strtolower(preg_replace('/\s+/', ' ', trim((string) ($item['name'] ?? ''))));
            if ($nameKey !== '') {
                $featuredMetaByName[$nameKey] = $meta;
            }
        }

        $bookingDoctors = $bookingDoctors->map(function ($doctor) use ($featuredMetaByAdminId, $featuredMetaByPhone, $featuredMetaByName, $text, $pickLocalizedText) {
            $doctorId = (int) ($doctor['id'] ?? 0);
            $phoneKey = trim((string) ($doctor['phone'] ?? ''));
            $nameKey = strtolower(preg_replace('/\s+/', ' ', trim((string) ($doctor['name'] ?? ''))));

            $meta = $featuredMetaByAdminId[$doctorId] ?? null;
            if (!$meta && $phoneKey !== '') {
                $meta = $featuredMetaByPhone[$phoneKey] ?? null;
            }
            if (!$meta && $nameKey !== '') {
                $meta = $featuredMetaByName[$nameKey] ?? null;
            }

            $specialty = $pickLocalizedText((string) ($meta['specialty'] ?? $text['consultant_doctor']));
            $designation = $pickLocalizedText((string) ($meta['designation'] ?? ''));
            $experience = $pickLocalizedText((string) ($meta['experience'] ?? ''));

            $infoParts = array_filter([
                $specialty,
                $designation,
                $experience !== '' ? ($text['experience_label'] . ': ' . $experience) : '',
            ]);

            $doctor['specialty'] = $specialty;
            $doctor['designation'] = $designation;
            $doctor['experience'] = $experience;
            $doctor['info'] = implode(' | ', $infoParts);

            return $doctor;
        })->values();

        $parseStringList = function (?string $rawJson, array $fallback) use ($pickLocalizedText): array {
            $rawJson = trim((string) $rawJson);
            if ($rawJson === '') {
                return $fallback;
            }

            $decoded = json_decode($rawJson, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return $fallback;
            }

            $normalized = collect($decoded)
                ->map(function ($item) use ($pickLocalizedText) {
                    if (is_string($item)) {
                        return $pickLocalizedText($item);
                    }

                    if (is_array($item)) {
                        $rawLabel = $item['label'] ?? $item['label_en'] ?? $item['en'] ?? '';
                        return $pickLocalizedText($rawLabel);
                    }

                    return '';
                })
                ->filter(fn ($item) => $item !== '')
                ->values()
                ->toArray();

            return empty($normalized) ? $fallback : $normalized;
        };

        $serviceItems = $parseStringList(
            $webSetting?->website_services_json,
            $locale === 'bn'
                ? ['ইমার্জেন্সি ও ট্রমা', 'ডায়াগনস্টিকস', 'বিশেষজ্ঞ পরামর্শ']
                : ['Emergency & Trauma', 'Diagnostics', 'Specialist Consultation']
        );

        $facilityItems = $parseStringList(
            $webSetting?->website_facilities_json,
            $locale === 'bn'
                ? ['ডিজিটাল টোকেন সিস্টেম', 'ইন-হাউস ফার্মেসি', 'ক্যাশলেস বিলিং', 'অনলাইন রিপোর্ট ডেলিভারি', 'ডেডিকেটেড হেল্প ডেস্ক']
                : ['Digital Queue & Token', 'In-house Pharmacy', 'Cashless Billing Ready', 'Online Report Delivery', 'Dedicated Help Desk']
        );

        $parseTestimonials = function (?string $rawJson, array $fallback): array {
            $rawJson = trim((string) $rawJson);
            if ($rawJson === '') {
                return $fallback;
            }

            $decoded = json_decode($rawJson, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return $fallback;
            }

            $normalized = collect($decoded)
                ->filter(fn ($item) => is_array($item))
                ->map(function ($item) {
                    return [
                        'name' => trim((string) ($item['name'] ?? '')),
                        'role' => trim((string) ($item['role'] ?? '')),
                        'quote' => trim((string) ($item['quote'] ?? '')),
                    ];
                })
                ->filter(fn ($item) => $item['name'] !== '' && $item['quote'] !== '')
                ->values()
                ->toArray();

            return empty($normalized) ? $fallback : $normalized;
        };

        $testimonialItems = [
            'en' => $parseTestimonials(
                $webSetting?->website_testimonials_en_json,
                [
                    [
                        'name' => 'Mehjabin Rahman',
                        'role' => 'Cardiac Care',
                        'quote' => 'The consultant team listened carefully and explained every step of my treatment plan.',
                    ],
                    [
                        'name' => 'Atikur Islam',
                        'role' => 'Emergency Support',
                        'quote' => 'We received very fast emergency support and the workflow was highly organized.',
                    ],
                    [
                        'name' => 'Sabiha Nowshin',
                        'role' => 'Diagnostic Service',
                        'quote' => 'Diagnostic reports were delivered quickly and follow-up guidance was very clear.',
                    ],
                ]
            ),
            'bn' => $parseTestimonials(
                $webSetting?->website_testimonials_bn_json,
                [
                    [
                        'name' => 'মেহজাবিন রহমান',
                        'role' => 'কার্ডিয়াক কেয়ার',
                        'quote' => 'ডাক্তার টিম খুব মনোযোগ দিয়ে শুনেছেন এবং চিকিৎসা পরিকল্পনা পরিষ্কারভাবে বুঝিয়েছেন।',
                    ],
                    [
                        'name' => 'আতিকুর ইসলাম',
                        'role' => 'ইমার্জেন্সি সাপোর্ট',
                        'quote' => 'জরুরি সময়ে দ্রুত সেবা পেয়েছি, পুরো প্রক্রিয়া খুবই সংগঠিত ছিল।',
                    ],
                    [
                        'name' => 'সাবিহা নওশিন',
                        'role' => 'ডায়াগনস্টিক সার্ভিস',
                        'quote' => 'রিপোর্ট ডেলিভারি দ্রুত ছিল এবং ফলোআপ গাইডলাইন খুব সহায়ক লেগেছে।',
                    ],
                ]
            ),
        ];

        // If admin selected a file-based website template, try rendering it directly
        $template = null;
        try {
            $opts = $webSetting?->attendance_device_options ?? null;
            if (is_string($opts)) {
                $decoded = json_decode($opts, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $opts = $decoded;
                }
            }
            if (is_array($opts) && !empty($opts['website_template'])) {
                $template = trim((string) $opts['website_template']);
                $template = preg_replace('/\.blade(\.php)?$/i', '', $template);
            }
        } catch (\Throwable $_) {
            $template = null;
        }

        if ($template) {
            $templatePath = resource_path('views/frontend/templates/' . $template . '.blade.php');
            if (is_file($templatePath)) {
                return view('frontend.templates.' . $template, [
                    'webSetting' => $webSetting,
                    'featuredDoctors' => $featuredDoctors,
                    'bookingDoctors' => $bookingDoctors,
                    'serviceItems' => $serviceItems,
                    'facilityItems' => $facilityItems,
                    'testimonialItems' => $testimonialItems,
                    'initialSection' => $initialSection,
                ]);
            }
        }

        return Inertia::render('Frontend/Home', [
            'webSetting' => fn () => $webSetting,
            'featuredDoctors' => fn () => $featuredDoctors,
            'bookingDoctors' => fn () => $bookingDoctors,
            'serviceItems' => fn () => $serviceItems,
            'facilityItems' => fn () => $facilityItems,
            'testimonialItems' => fn () => $testimonialItems,
            'initialSection' => fn () => $initialSection,
        ]);
    }

    private function redirectIfWebsiteDisabled($webSetting): ?RedirectResponse
    {
        if (!$webSetting) {
            return null;
        }

        $rawValue = $webSetting->getRawOriginal('website_enabled');
        $isEnabled = is_null($rawValue) ? true : (bool) $rawValue;

        if ($isEnabled) {
            return null;
        }

        return redirect()->route('backend.auth.login2');
    }

    public function storeAppointment(Request $request)
    {
        if ($redirect = $this->redirectIfWebsiteDisabled(get_cached_web_setting())) {
            return $redirect;
        }

        $locale = strtolower((string) app()->getLocale());
        if (!in_array($locale, ['en', 'bn'], true)) {
            $locale = 'en';
        }

        $messages = [
            'duplicate' => $locale === 'bn'
                ? 'একই রিকোয়েস্ট বারবার পাওয়া গেছে। কয়েক মিনিট পরে আবার চেষ্টা করুন।'
                : 'Duplicate request detected. Please wait a few minutes before trying again.',
            'failed' => $locale === 'bn'
                ? 'অ্যাপয়েন্টমেন্ট রিকোয়েস্ট জমা হয়নি। আবার চেষ্টা করুন।'
                : 'Failed to submit appointment request. Please try again.',
            'success' => $locale === 'bn'
                ? 'অ্যাপয়েন্টমেন্ট রিকোয়েস্ট সফলভাবে জমা হয়েছে।'
                : 'Appointment request submitted successfully.',
        ];

        $validated = $request->validate([
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_phone' => ['required', 'string', 'max:50'],
            'patient_email' => ['nullable', 'email', 'max:255'],
            'doctor_id' => ['required', 'integer', 'exists:admins,id'],
            'appointment_date' => ['required', 'date'],
            'message' => ['nullable', 'string', 'max:2000'],
            'website_url' => ['nullable', 'max:0'],
        ]);

        $isDuplicate = Appoinment::query()
            ->where('booking_source', 'website')
            ->where('doctor_id', (int) $validated['doctor_id'])
            ->where('website_contact_phone', $validated['patient_phone'])
            ->where('appoinment_date', $validated['appointment_date'])
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($isDuplicate) {
            return back()->with('errorMessage', $messages['duplicate']);
        }

        DB::beginTransaction();

        try {
            $patient = Patient::query()
                ->where('phone', $validated['patient_phone'])
                ->whereNull('deleted_at')
                ->first();

            if (!$patient) {
                $patient = Patient::create([
                    'name' => $validated['patient_name'],
                    'phone' => $validated['patient_phone'],
                    'email' => $validated['patient_email'] ?? null,
                    'status' => 'Active',
                ]);
            }

            $lastAppointmentId = Appoinment::query()->max('id') ?? 0;
            $nextId = $lastAppointmentId + 1;

            Appoinment::create([
                'patient_id' => $patient->id,
                'doctor_id' => (int) $validated['doctor_id'],
                'doctor_fee' => 0,
                'shift' => 'Morning',
                'appoinment_date' => $validated['appointment_date'],
                'slot' => 'Morning',
                'appointment_priority' => 'Normal',
                'payment_mode' => 'Cash',
                'transaction_id' => prefixed_serial('transaction_id_prefix', 'TRID', $nextId, 6),
                'booking_source' => 'website',
                'website_contact_phone' => $validated['patient_phone'],
                'discount_percentage' => 0,
                'appoinment_status' => 'Pending',
                'live_consultant' => 'No',
                'message' => $validated['message'] ?? null,
                'status' => 'Active',
            ]);

            // After creating appointment, ensure the selected doctor exists in CMS featured doctors
            try {
                $doctor = Admin::find((int) $validated['doctor_id']);
                if ($doctor) {
                    $ws = WebSetting::first();
                    if (!$ws) {
                        $ws = WebSetting::create([]);
                    }

                    $raw = trim((string) ($ws->website_featured_doctors_json ?? ''));
                    $decoded = [];
                    if ($raw !== '') {
                        $decoded = json_decode($raw, true);
                        if (!is_array($decoded)) {
                            $decoded = [];
                        }
                    }

                    $found = false;
                    foreach ($decoded as $item) {
                        if (!empty($item['admin_id']) && (int) $item['admin_id'] === (int) $doctor->id) {
                            $found = true;
                            break;
                        }
                        if (!empty($doctor->phone) && !empty($item['phone']) && trim($item['phone']) === trim($doctor->phone)) {
                            $found = true;
                            break;
                        }
                        if (!empty($item['name']) && trim($item['name']) === trim($doctor->name)) {
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        $decoded[] = [
                            'name' => trim((string) $doctor->name),
                            'specialty' => 'Consultant Doctor',
                            'designation' => '',
                            'phone' => $doctor->phone ?? '',
                            'experience' => '',
                            'bio' => '',
                            'image_url' => $doctor->photo ?? '',
                            'admin_id' => $doctor->id,
                        ];

                        $ws->website_featured_doctors_json = json_encode($decoded);
                        $ws->save();
                        if (function_exists('get_cached_web_setting')) {
                            get_cached_web_setting(true);
                        }
                        Log::info('storeAppointment: synced selected doctor to website_featured_doctors_json', ['doctor_id' => $doctor->id]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('storeAppointment: failed to sync selected doctor to CMS', ['error' => $e->getMessage()]);
            }

            DB::commit();

            return back()->with('successMessage', $messages['success']);
        } catch (Throwable $exception) {
            DB::rollBack();
            return back()->with('errorMessage', $messages['failed']);
        }
    }
}
