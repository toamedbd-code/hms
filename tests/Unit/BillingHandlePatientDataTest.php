<?php

namespace Tests\Unit;

use App\Http\Controllers\Backend\BillingController;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingHandlePatientDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_patient_age_and_dob_are_persisted_on_first_store_path(): void
    {
        $patient = Patient::create([
            'name' => 'Old Patient',
            'phone' => '01700000000',
            'gender' => 'Male',
            'dob' => '2020-01-01',
            'age' => '1 year',
        ]);

        $controller = app(BillingController::class);
        $method = new \ReflectionMethod($controller, 'handlePatientData');
        $method->setAccessible(true);

        $data = [
            'is_new_patient' => false,
            'patient_id' => $patient->id,
            'patient_name' => 'Old Patient',
            'patient_phone' => '01700000000',
            'patient_mobile' => '01700000000',
            'patient_gender' => 'Female',
            'gender' => 'Female',
            'dob' => '2024-02-15',
            'patient_age' => '2 years 5 months',
        ];

        $result = $method->invoke($controller, $data);

        $patient->refresh();

        $this->assertSame($patient->id, $result['patient_id']);
        $this->assertSame('2024-02-15', $patient->dob);
        $this->assertSame('2 years 5 months', $patient->age);
        $this->assertSame('Female', $patient->gender);
    }
}
