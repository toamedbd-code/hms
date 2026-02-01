<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PathologySeeder extends Seeder
{
    public function run()
    {
        // Seed testcategories table
        $parentCategories = [
            ['name' => 'Hematology',  'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Biochemistry',  'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Microbiology',  'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('testcategories')->insert($parentCategories);
        $parentIds = DB::table('testcategories')->pluck('id', 'name');
        $childCategories = [
            ['name' => 'Complete Blood Count',  'parent_id' => $parentIds['Hematology'], 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Liver Function Test',  'parent_id' => $parentIds['Biochemistry'], 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Urine Culture',  'parent_id' => $parentIds['Microbiology'], 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('testcategories')->insert($childCategories);

        // Seed pathologyunits table
        DB::table('pathologyunits')->insert([
            ['name' => 'g/dL', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'mg/dL', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'cells/µL', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'IU/L', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'mmol/L', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Seed pathologyparameters table
        $units = DB::table('pathologyunits')->pluck('id');

        DB::table('pathologyparameters')->insert([
            [
                'name' => 'Hemoglobin',
                'referance_from' => '12.0',
                'referance_to' => '16.0',
                'pathology_unit_id' => $units[0],
                'description' => 'Hemoglobin level in blood',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Blood Glucose',
                'referance_from' => '70',
                'referance_to' => '100',
                'pathology_unit_id' => $units[1],
                'description' => 'Fasting blood glucose level',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'White Blood Cells',
                'referance_from' => '4000',
                'referance_to' => '11000',
                'pathology_unit_id' => $units[2],
                'description' => 'Total WBC count',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'ALT (SGPT)',
                'referance_from' => '7',
                'referance_to' => '56',
                'pathology_unit_id' => $units[3],
                'description' => 'Alanine aminotransferase enzyme',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Cholesterol',
                'referance_from' => '120',
                'referance_to' => '200',
                'pathology_unit_id' => $units[1],
                'description' => 'Total cholesterol level',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
