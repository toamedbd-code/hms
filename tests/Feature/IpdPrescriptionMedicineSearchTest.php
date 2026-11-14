<?php

namespace Tests\Feature;

use App\Models\MedicineInventory;
use App\Models\MedicineCategory;
use App\Models\MedicineSupplier;
use App\Models\Admin;
use App\Models\OpdPatient;
use App\Models\OpdPrescription;
use App\Models\OpdPrescriptionItem;
use App\Models\Patient;
use App\Models\IpdPatient;
use App\Models\IpdPrescription;
use App\Models\IpdPrescriptionMedicine;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpdPrescriptionMedicineSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_ipd_prescription_medicine_search_returns_matching_inventory_names(): void
    {
        $this->withoutMiddleware();

        MedicineInventory::query()->create([
            'supplier_id' => 1,
            'medicine_category_id' => 1,
            'medicine_name' => 'Napa Extra',
            'medicine_unit_purchase_price' => 8,
            'medicine_unit_selling_price' => 10,
            'medicine_total_purchase_price' => 80,
            'medicine_total_selling_price' => 100,
            'medicine_quantity' => 10,
            'status' => 'Active',
        ]);

        $this->getJson(route('backend.ipdpatient.prescription.medicines.suggest', ['q' => 'Napa']))
            ->assertOk()
            ->assertJsonPath('results.0.name', 'Napa Extra');
    }

    public function test_prescription_medicine_suggestions_remember_previous_dosage_fields(): void
    {
        $this->withoutMiddleware();

        $patient = Patient::create([
            'name' => 'Prescription Memory Patient',
            'phone' => '01910101010',
            'gender' => 'Male',
            'status' => 'Active',
        ]);
        $doctor = Admin::create([
            'first_name' => 'Memory',
            'last_name' => 'Doctor',
            'email' => 'prescription-memory@example.test',
            'phone' => '01910101011',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);
        $this->actingAs($doctor, 'admin');
        $opdPatient = OpdPatient::create([
            'patient_id' => $patient->id,
            'consultant_doctor_id' => $doctor->id,
            'appointment_date' => '2026-10-22',
        ]);
        $prescription = OpdPrescription::create([
            'opd_patient_id' => $opdPatient->id,
            'created_by' => $doctor->id,
            'updated_by' => $doctor->id,
        ]);
        OpdPrescriptionItem::create([
            'opd_prescription_id' => $prescription->id,
            'medicine_name' => 'Memory Medicine',
            'dose' => '1 tablet',
            'frequency' => '1-0-1',
            'duration' => '5 days',
            'instructions' => 'After meal',
        ]);

        $this->getJson(route('backend.opdpatient.prescription.medicines.suggest', ['q' => 'Memory']))
            ->assertOk()
            ->assertJsonPath('results.0.name', 'Memory Medicine')
            ->assertJsonPath('results.0.dose', '1 tablet')
            ->assertJsonPath('results.0.frequency', '1-0-1')
            ->assertJsonPath('results.0.duration', '5 days')
            ->assertJsonPath('results.0.instructions', 'After meal')
            ->assertJsonPath('results.0.dose_options.0', '1 tablet')
            ->assertJsonPath('results.0.frequency_options.0', '1-0-1')
            ->assertJsonPath('results.0.duration_options.0', '5 days')
            ->assertJsonPath('results.0.instructions_options.0', 'After meal');

        $this->post(route('backend.opdpatient.prescription.store', $opdPatient->id), [
            'items' => [
                ['medicine_name' => 'Duplicate Medicine', 'dose' => '1 tablet'],
                ['medicine_name' => ' duplicate   medicine ', 'dose' => '2 tablets'],
            ],
            'tests' => [],
        ])->assertRedirect();

        $this->assertSame(
            1,
            OpdPrescriptionItem::query()
                ->where('opd_prescription_id', $prescription->id)
                ->where('medicine_name', 'Duplicate Medicine')
                ->count()
        );
    }

    public function test_pharmacy_billing_search_accepts_opd_patient_id_and_returns_prescribed_medicines(): void
    {
        $this->withoutMiddleware();

        $admin = Admin::create([
            'first_name' => 'OPD',
            'last_name' => 'Doctor',
            'email' => 'opd-search@example.test',
            'phone' => '01911111112',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);
        $patient = Patient::create([
            'name' => 'OPD Search Patient',
            'phone' => '01922222223',
            'gender' => 'Female',
            'status' => 'Active',
        ]);
        $opdPatient = OpdPatient::create([
            'patient_id' => $patient->id,
            'consultant_doctor_id' => $admin->id,
            'appointment_date' => '2026-10-22',
        ]);
        $prescription = OpdPrescription::create([
            'opd_patient_id' => $opdPatient->id,
            'created_by' => $admin->id,
        ]);
        OpdPrescriptionItem::create([
            'opd_prescription_id' => $prescription->id,
            'medicine_name' => 'OPD Test Medicine',
            'dose' => '1 tablet',
            'duration' => '3 days',
        ]);

        $supplier = MedicineSupplier::create([
            'name' => 'OPD Search Supplier',
            'phone' => '01933333334',
            'contact_person_name' => 'Contact',
            'contact_person_phone' => '01944444445',
            'drug_lisence_no' => 'LIC-OPD-SEARCH',
            'address' => 'Test address',
            'status' => 'Active',
        ]);
        $category = MedicineCategory::create(['name' => 'OPD Search Category', 'status' => 'Active']);
        $medicine = MedicineInventory::create([
            'supplier_id' => $supplier->id,
            'medicine_category_id' => $category->id,
            'medicine_name' => 'OPD Test Medicine',
            'medicine_unit_purchase_price' => 5,
            'medicine_unit_selling_price' => 10,
            'medicine_total_purchase_price' => 50,
            'medicine_total_selling_price' => 100,
            'medicine_quantity' => 10,
            'status' => 'Active',
        ]);

        $this->getJson(route('backend.billing.prescriptions.search', [
            'prescription_id' => 'OPD-' . $opdPatient->id,
        ]))
            ->assertOk()
            ->assertJsonPath('source', 'OPD')
            ->assertJsonPath('patient.id', $patient->id)
            ->assertJsonPath('medicines.0.medicine_id', $medicine->id)
            ->assertJsonPath('medicines.0.quantity', 1);
    }

    public function test_pharmacy_billing_search_accepts_ipd_patient_and_prescription_ids(): void
    {
        $this->withoutMiddleware();

        $admin = Admin::create([
            'first_name' => 'IPD',
            'last_name' => 'Doctor',
            'email' => 'ipd-search@example.test',
            'phone' => '01955555556',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);
        $patient = Patient::create([
            'name' => 'IPD Search Patient',
            'phone' => '01966666667',
            'gender' => 'Male',
            'status' => 'Active',
        ]);
        $floorId = DB::table('floors')->insertGetId([
            'name' => 'IPD Search Floor',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bedGroupId = DB::table('bedgroups')->insertGetId([
            'name' => 'IPD Search Group',
            'floor_id' => $floorId,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bedTypeId = DB::table('bedtypes')->insertGetId([
            'name' => 'IPD Search Type',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bedId = DB::table('beds')->insertGetId([
            'name' => 'IPD Search Bed',
            'bed_type_id' => $bedTypeId,
            'bed_group_id' => $bedGroupId,
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $ipdPatient = IpdPatient::create([
            'patient_id' => $patient->id,
            'consultant_doctor_id' => $admin->id,
            'admission_date' => '2026-10-22 10:00:00',
            'bed_group_id' => $bedGroupId,
            'bed_id' => $bedId,
            'status' => 'Active',
        ]);
        $prescription = IpdPrescription::create([
            'ipd_patient_id' => $ipdPatient->id,
            'patient_id' => $patient->id,
            'doctor_id' => $admin->id,
            'created_by' => $admin->id,
        ]);
        IpdPrescriptionMedicine::create([
            'ipd_prescription_id' => $prescription->id,
            'medicine_name' => 'IPD Test Medicine',
        ]);

        $supplier = MedicineSupplier::create([
            'name' => 'IPD Search Supplier',
            'phone' => '01977777778',
            'contact_person_name' => 'Contact',
            'contact_person_phone' => '01988888889',
            'drug_lisence_no' => 'LIC-IPD-SEARCH',
            'address' => 'Test address',
            'status' => 'Active',
        ]);
        $category = MedicineCategory::create(['name' => 'IPD Search Category', 'status' => 'Active']);
        $medicine = MedicineInventory::create([
            'supplier_id' => $supplier->id,
            'medicine_category_id' => $category->id,
            'medicine_name' => 'IPD Test Medicine',
            'medicine_unit_purchase_price' => 5,
            'medicine_unit_selling_price' => 12,
            'medicine_total_purchase_price' => 50,
            'medicine_total_selling_price' => 120,
            'medicine_quantity' => 10,
            'status' => 'Active',
        ]);

        foreach (['IPD-' . $ipdPatient->id, 'IPD-RX-' . $prescription->id] as $identifier) {
            $this->getJson(route('backend.billing.prescriptions.search', [
                'prescription_id' => $identifier,
            ]))
                ->assertOk()
                ->assertJsonPath('ipd_patient_id', $ipdPatient->id)
                ->assertJsonPath('ipd_prescription_id', $prescription->id)
                ->assertJsonPath('medicines.0.medicine_id', $medicine->id);
        }
    }
}
