<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChargeSystemSeeder extends Seeder
{
    public function run()
    {
        DB::table('chargeunittypes')->insert([
            ['name' => 'Each', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Hour', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Day', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Session', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Package', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('chargetaxcategories')->insert([
            ['name' => 'No Tax', 'percentage' => 0.00, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Standard VAT', 'percentage' => 5.00, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Reduced VAT', 'percentage' => 2.50, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Healthcare Tax', 'percentage' => 1.50, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Service Charge', 'percentage' => 10.00, 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('chargetypes')->insert([
            ['name' => 'Consultation', 'modules' => json_encode(['Appointment', 'OPD']), 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Procedure', 'modules' => json_encode(['IPD', 'OPD']), 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Diagnostic', 'modules' => json_encode(['Pathology', 'Radiology']), 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Emergency', 'modules' => json_encode(['Ambulance', 'IPD']), 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Support', 'modules' => json_encode(['Blood Bank', 'Ambulance']), 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $chargeTypes = DB::table('chargetypes')->pluck('id');

        DB::table('chargecategories')->insert([
            [
                'charge_type_id' => $chargeTypes[0],
                'name' => 'Doctor Consultation',
                'description' => 'Fees for doctor visits and consultations',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'charge_type_id' => $chargeTypes[1],
                'name' => 'Surgical Procedures',
                'description' => 'Charges for surgical operations',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'charge_type_id' => $chargeTypes[2],
                'name' => 'Lab Tests',
                'description' => 'Diagnostic laboratory tests',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'charge_type_id' => $chargeTypes[3],
                'name' => 'Emergency Services',
                'description' => 'Charges for emergency treatments',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'charge_type_id' => $chargeTypes[4],
                'name' => 'Blood Products',
                'description' => 'Charges for blood and blood products',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);

        $chargeCategories = DB::table('chargecategories')->pluck('id');
        $unitTypes = DB::table('chargeunittypes')->pluck('id');
        $taxCategories = DB::table('chargetaxcategories')->pluck('id');

        DB::table('charges')->insert([
            [
                'name' => 'General Physician Consultation',
                'charge_type_id' => $chargeTypes[0],
                'charge_category_id' => $chargeCategories[0],
                'unit_type_id' => $unitTypes[0],
                'tax_category_id' => $taxCategories[1],
                'tax' => 5.00,
                'standard_charge' => 500.00,
                'description' => 'Consultation fee for general physician',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Appendectomy',
                'charge_type_id' => $chargeTypes[1],
                'charge_category_id' => $chargeCategories[1],
                'unit_type_id' => $unitTypes[0],
                'tax_category_id' => $taxCategories[2],
                'tax' => 2.50,
                'standard_charge' => 25000.00,
                'description' => 'Surgical removal of appendix',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'CBC Test',
                'charge_type_id' => $chargeTypes[2],
                'charge_category_id' => $chargeCategories[2],
                'unit_type_id' => $unitTypes[0],
                'tax_category_id' => $taxCategories[1],
                'tax' => 5.00,
                'standard_charge' => 300.00,
                'description' => 'Complete Blood Count test',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Emergency Room Visit',
                'charge_type_id' => $chargeTypes[3],
                'charge_category_id' => $chargeCategories[3],
                'unit_type_id' => $unitTypes[0],
                'tax_category_id' => $taxCategories[3],
                'tax' => 1.50,
                'standard_charge' => 1000.00,
                'description' => 'Basic emergency room visit charge',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Whole Blood Unit',
                'charge_type_id' => $chargeTypes[4],
                'charge_category_id' => $chargeCategories[4],
                'unit_type_id' => $unitTypes[0],
                'tax_category_id' => $taxCategories[0],
                'tax' => 0.00,
                'standard_charge' => 1500.00,
                'description' => 'One unit of whole blood',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}
