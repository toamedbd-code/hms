<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Bed;
use App\Models\BedGroup;
use App\Models\BedType;
use App\Models\Floor;
use App\Models\IpdPatient;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IpdPrescriptionVitalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ipd_prescription_saves_vitals_and_examinations(): void
    {
        $this->withoutMiddleware();

        $doctor = Admin::query()->create([
            'first_name' => 'IPD',
            'last_name' => 'Doctor',
            'email' => 'ipd-prescription-doctor@example.test',
            'phone' => '01900000090',
            'password' => bcrypt('password'),
            'status' => 'Active',
        ]);
        $this->actingAs($doctor, 'admin');

        $patient = Patient::query()->create([
            'name' => 'IPD Prescription Patient',
            'phone' => '01700000090',
            'gender' => 'Male',
        ]);
        $floor = Floor::query()->create(['name' => 'Prescription Floor', 'description' => '', 'status' => 'Active']);
        $bedGroup = BedGroup::query()->create(['name' => 'Prescription Group', 'floor_id' => $floor->id, 'status' => 'Active']);
        $bedType = BedType::query()->create([
            'name' => 'Prescription Bed Type',
            'room_rent_rate_per_day' => 0,
            'bed_charge_rate_per_day' => 0,
        ]);
        $bed = Bed::query()->create([
            'name' => 'Prescription Bed',
            'bed_type_id' => $bedType->id,
            'bed_group_id' => $bedGroup->id,
            'status' => 'Active',
        ]);
        $ipdPatient = IpdPatient::query()->create([
            'patient_id' => $patient->id,
            'consultant_doctor_id' => $doctor->id,
            'admission_date' => now(),
            'bed_group_id' => $bedGroup->id,
            'bed_id' => $bed->id,
            'status' => 'Active',
        ]);

        $response = $this->post(route('backend.ipdpatient.prescription.store', $ipdPatient->id), [
            'doctor_id' => $doctor->id,
            'nibp' => '120/80 mmHg',
            'weight' => '65 kg',
            'pulse' => '72 bpm',
            'temperature' => '37 °C',
            'complaints' => 'Headache',
            'examinations' => 'No abnormal findings',
            'diagnosis' => 'Migraine',
            'advice' => 'Rest',
            'follow_up_date' => now()->addWeek()->toDateString(),
            'medicines' => [
                [
                    'medicine_name' => 'Sample tablet',
                    'dose' => '1 tab',
                    'frequency' => '1-0-1',
                    'duration' => '7 days',
                    'instructions' => 'After meal',
                ],
                [
                    'medicine_name' => ' sample   TABLET ',
                    'dose' => '2 tabs',
                    'frequency' => '1-0-0',
                    'duration' => '3 days',
                    'instructions' => 'Before meal',
                ],
            ],
            'tests' => ['CBC'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('ipd_prescriptions', [
            'ipd_patient_id' => $ipdPatient->id,
            'nibp' => '120/80 mmHg',
            'weight' => '65 kg',
            'pulse' => '72 bpm',
            'temperature' => '37 °C',
            'examinations' => 'No abnormal findings',
        ]);
        $this->assertDatabaseCount('ipd_prescription_medicines', 1);
    }
}
