<?php

namespace Database\Seeders;

use App\Models\WebSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class WebSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach ($this->datas() as $key => $value) {
            WebSetting::create($value);
        }
    }

    private function datas()
    {
        return [
            [
                'company_name' => 'Toamed Hospital',
                'company_short_name' => 'TM',
                'phone' => '+1-234-567-8901',
                'logo' => 'assets/toamed.png',
                'icon' => 'assets/toamed.png',
                'report_title' => 'Toamed Official Report',
                'status' => 'Active',
                'website_hero_title' => 'Welcome to Toamed Hospital',
                'website_hero_subtitle' => 'Compassionate care, trusted doctors, and modern hospital services for your family.',
                'website_cta_text' => 'Book an Appointment Today',
                'website_services_json' => json_encode([
                    'Emergency & Trauma',
                    'Diagnostics',
                    'Specialist Consultation',
                    'Pharmacy',
                    'Vaccination',
                    'Day Care',
                ], JSON_UNESCAPED_UNICODE),
                'website_facilities_json' => json_encode([
                    'Digital Queue & Token',
                    'In-house Pharmacy',
                    'Cashless Billing Ready',
                    'Online Report Delivery',
                    'Dedicated Help Desk',
                ], JSON_UNESCAPED_UNICODE),
                'website_testimonials_en_json' => json_encode([
                    [
                        'name' => 'Mehjabin Rahman',
                        'role' => 'Cardiac Care',
                        'quote' => 'The consultant team listened carefully and explained every step of my treatment plan.'
                    ],
                    [
                        'name' => 'Atikur Islam',
                        'role' => 'Emergency Support',
                        'quote' => 'We received very fast emergency support and the workflow was highly organized.'
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'website_testimonials_bn_json' => json_encode([
                    [
                        'name' => 'মেহজাবিন রহমান',
                        'role' => 'কার্ডিয়াক কেয়ার',
                        'quote' => 'ডাক্তার টিম খুব মনোযোগ দিয়ে শুনেছেন এবং চিকিৎসা পরিকল্পনা পরিষ্কারভাবে বুঝিয়েছেন।'
                    ],
                    [
                        'name' => 'আতিকুর ইসলাম',
                        'role' => 'ইমার্জেন্সি সাপোর্ট',
                        'quote' => 'জরুরি সময়ে দ্রুত সেবা পেয়েছি, পুরো প্রক্রিয়া খুবই সংগঠিত ছিল।'
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'website_featured_doctors_json' => json_encode([
                    [
                        'name' => 'Dr. Ayesha Siddiqua',
                        'specialty' => 'Cardiology',
                        'designation' => 'Consultant',
                        'phone' => '01711111111',
                        'experience' => '10 years',
                        'bio' => 'Expert in cardiac care and patient counseling.',
                        'image_url' => '',
                    ],
                    [
                        'name' => 'Dr. Mahmud Hasan',
                        'specialty' => 'Medicine',
                        'designation' => 'Consultant',
                        'phone' => '01722222222',
                        'experience' => '8 years',
                        'bio' => 'Specialist in internal medicine and diabetes.',
                        'image_url' => '',
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'deleted_at' => null
            ],
        ];
    }
}
