<?php

namespace Database\Seeders;

use App\Models\Patient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PatientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach ($this->patientData() as $patient) {
            Patient::create($patient);
        }
    }

    private function patientData()
    {
        $now = Carbon::now();
        
        return [
            [
                'name' => 'John Smith',
                'guardian_name' => 'Mary Smith',
                'gender' => 'Male',
                'dob' => '1985-06-15',
                'age' => '38',
                'blood_group' => 'A+',
                'marital_status' => 'Married',
                'phone' => '01711234567',
                'email' => 'john.smith@example.com',
                'address' => '123 Main Street, Dhaka',
                'remarks' => 'Regular checkup patient',
                'any_known_allergies' => 'Penicillin',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'Fatima Begum',
                'guardian_name' => 'Abdul Rahman',
                'gender' => 'Female',
                'dob' => '1992-03-22',
                'age' => '31',
                'blood_group' => 'B+',
                'marital_status' => 'Single',
                'phone' => '01818765432',
                'email' => 'fatima.b@example.com',
                'address' => '456 Mirpur Road, Dhaka',
                'remarks' => 'Diabetic patient',
                'any_known_allergies' => 'None',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'Robert Johnson',
                'guardian_name' => 'Sarah Johnson',
                'gender' => 'Male',
                'dob' => '1978-11-05',
                'age' => '45',
                'blood_group' => 'O+',
                'marital_status' => 'Married',
                'phone' => '01917654321',
                'email' => 'robert.j@example.com',
                'address' => '789 Gulshan Avenue, Dhaka',
                'remarks' => 'Hypertension case',
                'any_known_allergies' => 'Shellfish',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'Ayesha Rahman',
                'guardian_name' => 'Mohammad Rahman',
                'gender' => 'Female',
                'dob' => '2005-09-18',
                'age' => '18',
                'blood_group' => 'AB+',
                'marital_status' => 'Single',
                'phone' => '01612345678',
                'email' => 'ayesha.r@example.com',
                'address' => '321 Banani Road, Dhaka',
                'remarks' => 'Pediatric patient',
                'any_known_allergies' => 'Dust mites',
                'created_at' => $now,
                'updated_at' => $now
            ],
            [
                'name' => 'David Wilson',
                'guardian_name' => 'Elizabeth Wilson',
                'gender' => 'Male',
                'dob' => '1960-07-30',
                'age' => '63',
                'blood_group' => 'A-',
                'marital_status' => 'Widowed',
                'phone' => '01518765432',
                'email' => 'david.w@example.com',
                'address' => '654 Dhanmondi, Dhaka',
                'remarks' => 'Geriatric care',
                'any_known_allergies' => 'Peanuts, Iodine contrast',
                'tpa_id' => 1,
                'tpa_code' => 'TPA12345',
                'tpa_validity' => '2025-12-31',
                'created_at' => $now,
                'updated_at' => $now
            ]
        ];
    }
}