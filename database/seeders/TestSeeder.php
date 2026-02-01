<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestSeeder extends Seeder
{
    public function run()
    {
        // Get category IDs
        $categories = DB::table('testcategories')->pluck('id', 'name');
        
        // Get parameter IDs for test_parameters JSON
        $parameters = DB::table('pathologyparameters')->get();
        
        $tests = [
            [
                'category_type' => 'Pathology',
                'test_name' => 'Complete Blood Count (CBC)',
                'test_short_name' => 'CBC',
                'test_type' => 'Laboratory',
                'test_category_id' => $categories['Complete Blood Count'],
                'test_sub_category_id' => null,
                'method' => 'Automated Hematology Analyzer',
                'report_days' => 1,
                'charge_category_id' => 1,
                'charge_name' => 'Laboratory Charges',
                'tax' => '5%',
                'standard_charge' => 800.00,
                'amount' => 800.00,
                'test_parameters' => json_encode([
                    $parameters->where('name', 'Hemoglobin')->first()->id ?? 1,
                    $parameters->where('name', 'White Blood Cells')->first()->id ?? 3,
                ]),
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'category_type' => 'Pathology',
                'test_name' => 'Liver Function Test',
                'test_short_name' => 'LFT',
                'test_type' => 'Laboratory',
                'test_category_id' => $categories['Liver Function Test'],
                'test_sub_category_id' => null,
                'method' => 'Spectrophotometry',
                'report_days' => 1,
                'charge_category_id' => 1,
                'charge_name' => 'Laboratory Charges',
                'tax' => '5%',
                'standard_charge' => 1200.00,
                'amount' => 1200.00,
                'test_parameters' => json_encode([
                    $parameters->where('name', 'ALT (SGPT)')->first()->id ?? 4,
                ]),
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'category_type' => 'Pathology',
                'test_name' => 'Fasting Blood Sugar',
                'test_short_name' => 'FBS',
                'test_type' => 'Laboratory',
                'test_category_id' => $categories['Biochemistry'],
                'test_sub_category_id' => null,
                'method' => 'Glucose Oxidase Method',
                'report_days' => 1,
                'charge_category_id' => 1,
                'charge_name' => 'Laboratory Charges',
                'tax' => '5%',
                'standard_charge' => 300.00,
                'amount' => 300.00,
                'test_parameters' => json_encode([
                    $parameters->where('name', 'Blood Glucose')->first()->id ?? 2,
                ]),
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'category_type' => 'Pathology',
                'test_name' => 'Lipid Profile',
                'test_short_name' => 'Lipid',
                'test_type' => 'Laboratory',
                'test_category_id' => $categories['Biochemistry'],
                'test_sub_category_id' => null,
                'method' => 'Enzymatic Method',
                'report_days' => 1,
                'charge_category_id' => 1,
                'charge_name' => 'Laboratory Charges',
                'tax' => '5%',
                'standard_charge' => 1500.00,
                'amount' => 1500.00,
                'test_parameters' => json_encode([
                    $parameters->where('name', 'Cholesterol')->first()->id ?? 5,
                ]),
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'category_type' => 'Pathology',
                'test_name' => 'Urine Culture & Sensitivity',
                'test_short_name' => 'U/C&S',
                'test_type' => 'Laboratory',
                'test_category_id' => $categories['Urine Culture'],
                'test_sub_category_id' => null,
                'method' => 'Culture Method',
                'report_days' => 3,
                'charge_category_id' => 1,
                'charge_name' => 'Laboratory Charges',
                'tax' => '5%',
                'standard_charge' => 600.00,
                'amount' => 600.00,
                'test_parameters' => json_encode([]),
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'category_type' => 'Pathology',
                'test_name' => 'Hemoglobin Level',
                'test_short_name' => 'Hb',
                'test_type' => 'Laboratory',
                'test_category_id' => $categories['Hematology'],
                'test_sub_category_id' => null,
                'method' => 'Cyanmethemoglobin Method',
                'report_days' => 1,
                'charge_category_id' => 1,
                'charge_name' => 'Laboratory Charges',
                'tax' => '5%',
                'standard_charge' => 200.00,
                'amount' => 200.00,
                'test_parameters' => json_encode([
                    $parameters->where('name', 'Hemoglobin')->first()->id ?? 1,
                ]),
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'category_type' => 'Radiology',
                'test_name' => 'Chest X-Ray',
                'test_short_name' => 'CXR',
                'test_type' => 'Imaging',
                'test_category_id' => $categories['Hematology'], // Using existing category for demo
                'test_sub_category_id' => null,
                'method' => 'Digital Radiography',
                'report_days' => 1,
                'charge_category_id' => 2,
                'charge_name' => 'Radiology Charges',
                'tax' => '5%',
                'standard_charge' => 1000.00,
                'amount' => 1000.00,
                'test_parameters' => json_encode([]),
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'category_type' => 'Pathology',
                'test_name' => 'Random Blood Sugar',
                'test_short_name' => 'RBS',
                'test_type' => 'Laboratory',
                'test_category_id' => $categories['Biochemistry'],
                'test_sub_category_id' => null,
                'method' => 'Glucose Oxidase Method',
                'report_days' => 1,
                'charge_category_id' => 1,
                'charge_name' => 'Laboratory Charges',
                'tax' => '5%',
                'standard_charge' => 250.00,
                'amount' => 250.00,
                'test_parameters' => json_encode([
                    $parameters->where('name', 'Blood Glucose')->first()->id ?? 2,
                ]),
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('tests')->insert($tests);
    }
}