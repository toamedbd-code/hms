<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MedicineSeeder extends Seeder
{
    public function run()
    {
        // Seed medicinegroups table
        $medicineGroups = [
            ['name' => 'Antibiotics', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Analgesics', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Antihypertensives', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Antidiabetic', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Vitamins & Minerals', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Gastrointestinal', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Respiratory', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Cardiovascular', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('medicinegroups')->insert($medicineGroups);

        // Seed medicinecompanies table
        $medicineCompanies = [
            ['name' => 'Square Pharmaceuticals Ltd.', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Incepta Pharmaceuticals Ltd.', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Beximco Pharmaceuticals Ltd.', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'ACI Limited', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Opsonin Pharma Limited', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Eskayef Bangladesh Limited', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Renata Limited', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Drug International Limited', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('medicinecompanies')->insert($medicineCompanies);

        // Seed medicineunits table
        $medicineUnits = [
            ['name' => 'mg', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'g', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'ml', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'IU', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'mcg', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'tablet', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'capsule', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'syrup', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('medicineunits')->insert($medicineUnits);

        // Seed dosedurations table
        $doseDurations = [
            ['name' => '3 days', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => '5 days', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => '7 days', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => '10 days', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => '14 days', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => '1 month', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => '3 months', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'As needed', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('dosedurations')->insert($doseDurations);

        // Seed medicinedoseintervals table
        $medicineDoseIntervals = [
            ['name' => 'Once daily', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Twice daily', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Thrice daily', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Four times daily', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Every 6 hours', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Every 8 hours', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Every 12 hours', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'As needed', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('medicinedoseintervals')->insert($medicineDoseIntervals);

        // Seed medicinecategories table
        $medicineCategories = [
            ['name' => 'Tablet', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Capsule', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Syrup', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Injection', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Cream/Ointment', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Drops', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Inhaler', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Suppository', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('medicinecategories')->insert($medicineCategories);

        // Get IDs for foreign key relationships
        $categoryIds = DB::table('medicinecategories')->pluck('id', 'name');
        $unitIds = DB::table('medicineunits')->pluck('id', 'name');

        // Seed medicinedosages table
        $medicineDosages = [
            [
                'medicine_category_id' => $categoryIds['Tablet'],
                'dose' => '500',
                'medicine_unit_id' => $unitIds['mg'],
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'medicine_category_id' => $categoryIds['Tablet'],
                'dose' => '250',
                'medicine_unit_id' => $unitIds['mg'],
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'medicine_category_id' => $categoryIds['Capsule'],
                'dose' => '250',
                'medicine_unit_id' => $unitIds['mg'],
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'medicine_category_id' => $categoryIds['Syrup'],
                'dose' => '125',
                'medicine_unit_id' => $unitIds['mg'],
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'medicine_category_id' => $categoryIds['Tablet'],
                'dose' => '5',
                'medicine_unit_id' => $unitIds['mg'],
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'medicine_category_id' => $categoryIds['Injection'],
                'dose' => '1',
                'medicine_unit_id' => $unitIds['g'],
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];
        DB::table('medicinedosages')->insert($medicineDosages);

        // Seed medicinesuppliers table
        $medicineSuppliers = [
            [
                'name' => 'Medico Pharma Distributors',
                'phone' => '01711234567',
                'contact_person_name' => 'Mr. Rahman',
                'contact_person_phone' => '01711234568',
                'drug_lisence_no' => 'DL-DHK-2023-001',
                'address' => 'Dhanmondi, Dhaka-1205',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Alpha Medical Supplies',
                'phone' => '01812345678',
                'contact_person_name' => 'Ms. Fatima',
                'contact_person_phone' => '01812345679',
                'drug_lisence_no' => 'DL-DHK-2023-002',
                'address' => 'Gulshan, Dhaka-1212',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Beta Pharmaceuticals Supply',
                'phone' => '01913456789',
                'contact_person_name' => 'Mr. Karim',
                'contact_person_phone' => '01913456790',
                'drug_lisence_no' => 'DL-DHK-2023-003',
                'address' => 'Motijheel, Dhaka-1000',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];
        DB::table('medicinesuppliers')->insert($medicineSuppliers);

        // Get supplier IDs
        $supplierIds = DB::table('medicinesuppliers')->pluck('id');

        // Seed medicineinventories table
        $medicineInventories = [
            [
                'supplier_id' => $supplierIds[0],
                'medicine_category_id' => $categoryIds['Tablet'],
                'medicine_name' => 'Paracetamol 500mg',
                'medicine_unit_purchase_price' => 2.50,
                'medicine_unit_selling_price' => 3.00,
                'medicine_total_purchase_price' => 2500.00,
                'medicine_total_selling_price' => 3000.00,
                'medicine_quantity' => 1000,
                'remarks' => 'Fever and pain relief',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'supplier_id' => $supplierIds[0],
                'medicine_category_id' => $categoryIds['Tablet'],
                'medicine_name' => 'Amoxicillin 500mg',
                'medicine_unit_purchase_price' => 8.00,
                'medicine_unit_selling_price' => 10.00,
                'medicine_total_purchase_price' => 4000.00,
                'medicine_total_selling_price' => 5000.00,
                'medicine_quantity' => 500,
                'remarks' => 'Antibiotic for bacterial infections',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'supplier_id' => $supplierIds[1],
                'medicine_category_id' => $categoryIds['Capsule'],
                'medicine_name' => 'Omeprazole 20mg',
                'medicine_unit_purchase_price' => 5.00,
                'medicine_unit_selling_price' => 6.50,
                'medicine_total_purchase_price' => 1500.00,
                'medicine_total_selling_price' => 1950.00,
                'medicine_quantity' => 300,
                'remarks' => 'Proton pump inhibitor for acid reflux',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'supplier_id' => $supplierIds[1],
                'medicine_category_id' => $categoryIds['Syrup'],
                'medicine_name' => 'Amoxicillin Suspension 125mg/5ml',
                'medicine_unit_purchase_price' => 45.00,
                'medicine_unit_selling_price' => 55.00,
                'medicine_total_purchase_price' => 4500.00,
                'medicine_total_selling_price' => 5500.00,
                'medicine_quantity' => 100,
                'remarks' => 'Pediatric antibiotic suspension',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'supplier_id' => $supplierIds[2],
                'medicine_category_id' => $categoryIds['Tablet'],
                'medicine_name' => 'Metformin 500mg',
                'medicine_unit_purchase_price' => 3.50,
                'medicine_unit_selling_price' => 4.50,
                'medicine_total_purchase_price' => 1750.00,
                'medicine_total_selling_price' => 2250.00,
                'medicine_quantity' => 500,
                'remarks' => 'Antidiabetic medication',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'supplier_id' => $supplierIds[2],
                'medicine_category_id' => $categoryIds['Tablet'],
                'medicine_name' => 'Atorvastatin 20mg',
                'medicine_unit_purchase_price' => 12.00,
                'medicine_unit_selling_price' => 15.00,
                'medicine_total_purchase_price' => 3600.00,
                'medicine_total_selling_price' => 4500.00,
                'medicine_quantity' => 300,
                'remarks' => 'Cholesterol lowering medication',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'supplier_id' => $supplierIds[0],
                'medicine_category_id' => $categoryIds['Injection'],
                'medicine_name' => 'Ceftriaxone 1g Injection',
                'medicine_unit_purchase_price' => 120.00,
                'medicine_unit_selling_price' => 150.00,
                'medicine_total_purchase_price' => 12000.00,
                'medicine_total_selling_price' => 15000.00,
                'medicine_quantity' => 100,
                'remarks' => 'Broad spectrum antibiotic injection',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'supplier_id' => $supplierIds[1],
                'medicine_category_id' => $categoryIds['Tablet'],
                'medicine_name' => 'Aspirin 75mg',
                'medicine_unit_purchase_price' => 1.50,
                'medicine_unit_selling_price' => 2.00,
                'medicine_total_purchase_price' => 1500.00,
                'medicine_total_selling_price' => 2000.00,
                'medicine_quantity' => 1000,
                'remarks' => 'Low dose aspirin for cardioprotection',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];
        DB::table('medicineinventories')->insert($medicineInventories);
    }
}