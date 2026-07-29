<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\Bed;
use App\Models\IpdPatient;
use App\Models\Patient;

class IpdInvoiceItemRenderingTest extends TestCase
{
    public function test_ipd_invoice_view_shows_running_bill_items(): void
    {
        $patient = new Patient([
            'id' => 1,
            'name' => 'Test Patient',
            'age' => 30,
            'gender' => 'Male',
            'phone' => '01234567890',
        ]);

        $doctor = new Admin([
            'id' => 1,
            'name' => 'Dr. Test',
        ]);

        $bed = new Bed([
            'id' => 1,
            'name' => 'Bed 1',
        ]);

        $ipdPatient = new IpdPatient([
            'id' => 99,
            'admission_date' => now()->subDay(),
            'discharged_at' => now(),
            'status' => 'Inactive',
            'case' => 'Emergency',
        ]);
        $ipdPatient->setRelation('patient', $patient);
        $ipdPatient->setRelation('doctor', $doctor);
        $ipdPatient->setRelation('bed', $bed);

        $html = view('frontend.invoice.ipd-pdf', [
            'ipd_id' => 'IPDN0099',
            'ipdpatient' => $ipdPatient,
            'patient' => $patient,
            'doctor' => $doctor,
            'bed' => $bed,
            'payments' => collect(),
            'total_paid' => 0,
            'header_image' => '',
            'footer_image' => '',
            'footer_content' => '',
            'footer_content_position' => 'above',
            'barcode' => '',
            'printed_at' => now()->format('d-M-Y h:i:s A'),
            'medicineTotal' => 0,
            'labTotal' => 0,
            'runningLines' => [
                [
                    'item_name' => 'Test Consultation',
                    'quantity' => 1,
                    'net_amount' => 500,
                    'category' => 'IPD',
                ],
            ],
        ])->render();

        $this->assertStringContainsString('Test Consultation', $html);
    }
}
