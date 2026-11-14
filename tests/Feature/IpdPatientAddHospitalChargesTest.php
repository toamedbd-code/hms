<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Admin;
use App\Models\Patient;
use App\Models\IpdPatient;
use App\Models\Charge;
use App\Models\ChargeType;
use App\Models\ChargeCategory;
use App\Models\ChargeUnitType;
use App\Models\ChargeTaxCategory;
use App\Models\Billing;
use App\Models\BillItem;
use App\Models\BedType;
use App\Models\BedGroup;
use App\Models\Bed;
use App\Models\Floor;

class IpdPatientAddHospitalChargesTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_hospital_charges_creates_billing_and_items()
    {
        // Skip middleware to focus on controller behaviour
        $this->withoutMiddleware();

        // Create minimal patient and IPD admission
        $patient = Patient::create(['name' => 'Test Patient', 'phone' => '0123456789', 'gender' => 'Male']);

        // Create required related records for IPD admission
        // Create admin (doctor) directly to match migrations
        $doctor = Admin::create([
            'first_name' => 'Doc',
            'last_name' => 'One',
            'email' => 'doctor1@example.test',
            'phone' => '01900000000',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);
        // Authenticate as the admin for created_by FK
        $this->actingAs($doctor, 'admin');
        $floor = Floor::create(['name' => 'Floor 1', 'description' => '', 'status' => 'Active']);
        $bedGroup = BedGroup::create(['name' => 'Group A', 'floor_id' => $floor->id, 'status' => 'Active']);
        $bedType = BedType::create(['name' => 'Type 1', 'room_rent_rate_per_day' => 0, 'bed_charge_rate_per_day' => 0, 'status' => 'Active']);
        $bed = Bed::create(['name' => 'Bed 1', 'bed_type_id' => $bedType->id, 'bed_group_id' => $bedGroup->id, 'status' => 'Active']);

        $ipd = IpdPatient::create([
            'patient_id' => $patient->id,
            'consultant_doctor_id' => $doctor->id,
            'admission_date' => now(),
            'bed_group_id' => $bedGroup->id,
            'bed_id' => $bed->id,
            'status' => 'Active',
        ]);

        // Create required charge taxonomy records
        $ctype = ChargeType::create(['name' => 'General', 'modules' => '[]', 'status' => 'Active']);
        $ccat = ChargeCategory::create(['charge_type_id' => $ctype->id, 'name' => 'Medicine', 'description' => 'General medicines', 'status' => 'Active']);
        $cunit = ChargeUnitType::create(['name' => 'Unit', 'status' => 'Active']);
        $ctax = ChargeTaxCategory::create(['name' => 'No Tax', 'percentage' => 0, 'status' => 'Active']);

        // Create a charge
        $charge = Charge::create([
            'name' => 'Test Consultation',
            'charge_type_id' => $ctype->id,
            'charge_category_id' => $ccat->id,
            'unit_type_id' => $cunit->id,
            'tax_category_id' => $ctax->id,
            'standard_charge' => 500,
            'status' => 'Active',
        ]);

        // Ensure hospital charges are enabled for this test scenario
        config(['app.hospital_charges_enabled' => true]);
        putenv('HOSPITAL_CHARGES_ENABLED=true');
        $_ENV['HOSPITAL_CHARGES_ENABLED'] = 'true';
        $_SERVER['HOSPITAL_CHARGES_ENABLED'] = 'true';

        // Call endpoint
        $response = $this->postJson(route('backend.ipdpatient.charges.hospital.store', $ipd->id), [
            'hospital_charge_ids' => [$charge->id],
        ]);

        if ($response->status() !== 200) {
            echo "\nRESPONSE:\n" . $response->getContent() . "\n";
        }
        $response->assertStatus(200)->assertJson(['success' => true]);

        // Billing created and linked to IPD
        $this->assertDatabaseHas('billings', [
            'patient_id' => $patient->id,
            'status' => 'Active',
        ]);

        // BillItem for the charge exists
        $this->assertDatabaseHas('bill_items', [
            'item_id' => $charge->id,
            'item_name' => 'Test Consultation',
        ]);
    }

    public function test_ipd_form_charge_item_options_include_active_tests(): void
    {
        $this->withoutMiddleware();

        $doctor = Admin::create([
            'first_name' => 'Doc',
            'last_name' => 'Search',
            'email' => 'doctor.search@example.test',
            'phone' => '01900000012',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);
        $this->actingAs($doctor, 'admin');

        \App\Models\Test::create([
            'test_name' => 'CBC',
            'test_short_name' => 'CBC',
            'category_type' => 'Pathology',
            'amount' => 400,
            'standard_charge' => 400,
            'status' => 'Active',
        ]);

        $controller = $this->app->make(\App\Http\Controllers\Backend\IpdPatientController::class);
        $method = new \ReflectionMethod($controller, 'chargeItemOptions');
        $method->setAccessible(true);
        $options = $method->invoke($controller);

        $this->assertNotEmpty($options['pathologyAndRadiologyTests']);
        $this->assertSame('CBC', $options['pathologyAndRadiologyTests'][0]['test_name']);
        $this->assertSame(400.0, (float) $options['pathologyAndRadiologyTests'][0]['amount']);
    }

    public function test_ipd_patient_create_form_loads(): void
    {
        $this->withoutMiddleware();

        $doctor = Admin::create([
            'first_name' => 'Doc',
            'last_name' => 'Create',
            'email' => 'doctor.create@example.test',
            'phone' => '01900000013',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);
        $this->actingAs($doctor, 'admin');

        $this->get(route('backend.ipdpatient.create'))->assertOk();
    }

    public function test_ipd_symptom_type_is_saved_for_the_next_admission_form(): void
    {
        $this->withoutMiddleware();

        $controller = $this->app->make(\App\Http\Controllers\Backend\IpdPatientController::class);
        $method = new \ReflectionMethod($controller, 'rememberSymptomType');
        $method->setAccessible(true);

        $method->invoke($controller, ' Persistent symptom ');
        $method->invoke($controller, 'Persistent symptom');

        $this->assertDatabaseHas('symptom_types', [
            'name' => 'Persistent symptom',
            'status' => 'Active',
        ]);
        $this->assertSame(1, \App\Models\SymptomType::query()
            ->where('name', 'Persistent symptom')
            ->count());

        $inactiveType = \App\Models\SymptomType::create([
            'name' => 'Previously inactive symptom',
            'status' => 'Inactive',
        ]);
        $method->invoke($controller, $inactiveType->name);

        $this->assertDatabaseHas('symptom_types', [
            'id' => $inactiveType->id,
            'status' => 'Active',
        ]);
    }

    public function test_symptom_title_suggestions_use_saved_distinct_titles(): void
    {
        $this->withoutMiddleware();

        $patient = Patient::create([
            'name' => 'Symptom Title Patient',
            'phone' => '0123456788',
            'gender' => 'Male',
        ]);
        $doctor = Admin::create([
            'first_name' => 'Title',
            'last_name' => 'Doctor',
            'email' => 'symptom.title@example.test',
            'phone' => '01900000014',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);
        $floor = Floor::create(['name' => 'Title Floor', 'description' => '', 'status' => 'Active']);
        $bedGroup = BedGroup::create(['name' => 'Title Group', 'floor_id' => $floor->id, 'status' => 'Active']);
        $bedType = BedType::create([
            'name' => 'Title Bed Type',
            'room_rent_rate_per_day' => 0,
            'bed_charge_rate_per_day' => 0,
            'status' => 'Active',
        ]);
        $bed = Bed::create([
            'name' => 'Title Bed',
            'bed_type_id' => $bedType->id,
            'bed_group_id' => $bedGroup->id,
            'status' => 'Active',
        ]);

        foreach (['Persistent title', 'Persistent title', 'Another title', ''] as $title) {
            IpdPatient::create([
                'patient_id' => $patient->id,
                'consultant_doctor_id' => $doctor->id,
                'symptom_title' => $title,
                'admission_date' => now(),
                'bed_group_id' => $bedGroup->id,
                'bed_id' => $bed->id,
                'status' => 'Active',
            ]);
        }

        $controller = $this->app->make(\App\Http\Controllers\Backend\IpdPatientController::class);
        $method = new \ReflectionMethod($controller, 'symptomTitleOptions');
        $method->setAccessible(true);

        $this->assertSame(['Another title', 'Persistent title'], $method->invoke($controller));
    }
}
