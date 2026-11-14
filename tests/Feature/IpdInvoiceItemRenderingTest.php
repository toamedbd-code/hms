<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Admin;
use App\Models\Bed;
use App\Models\InvoiceDesign;
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
            'invoiceDesign' => (new InvoiceDesign())->forceFill([
                'active_pad_type' => 'institution',
                'full_page_pad_content_offset' => 12,
            ]),
            'fullPagePadImage' => 'data:image/png;base64,ZmFrZQ==',
            'ipd_id' => 'IPDN0099',
            'ipdpatient' => $ipdPatient,
            'patient' => $patient,
            'doctor' => $doctor,
            'bed' => $bed,
            'payments' => collect(),
            'total_paid' => 0,
            'header_image' => '',
            'footer_image' => '',
            'footer_content' => 'Software By: www.toamed.com,Dhaka. Support: 01919-592638',
            'footer_content_position' => 'above',
            'barcode' => '',
            'printed_at' => now()->format('d-M-Y h:i:s A'),
            'medicineTotal' => 0,
            'labTotal' => 0,
            'consultant' => "Dr. Example\nMBBS, FCPS\nBIRDEM\nDhaka, Bangladesh",
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
        $this->assertStringContainsString('.info-table td {', $html);
        $this->assertStringNotContainsString('.info-table td,', $html);
        $this->assertStringContainsString('.content-section.content-section', $html);
        $this->assertStringContainsString('@page', $html);
        $this->assertStringContainsString('size: auto;', $html);
        $this->assertStringContainsString('class="value refd-value" colspan="4">Dr. Example', $html);
        $this->assertStringContainsString("Dr. Example\nMBBS, FCPS\nBIRDEM\nDhaka, Bangladesh", $html);
        $this->assertStringContainsString('class="with-institution-pad"', $html);
        $this->assertStringContainsString('data:image/png;base64,ZmFrZQ==', $html);
        $this->assertStringContainsString('body.with-institution-pad .footer-wrapper', $html);
        $this->assertStringContainsString('Software By: www.toamed.com,Dhaka. Support: 01919-592638', $html);
        $this->assertStringContainsString('Printing Date &amp; Time:', $html);
        $this->assertStringContainsString('.footer-content-row', $html);
        $this->assertStringContainsString('color: #000 !important;', $html);
        $this->assertStringContainsString('body.with-institution-pad .footer-content-area', $html);
        $this->assertStringContainsString('.footer-content-area.above .footer-printed-at', $html);
        $this->assertStringContainsString('transform: none !important;', $html);
        $this->assertStringContainsString('width: max-content !important;', $html);
        $this->assertStringContainsString('box-sizing: border-box !important;', $html);
        $this->assertStringContainsString('padding: 0 5mm 0 5mm !important;', $html);
    }

    public function test_shared_invoice_print_layout_uses_the_print_dialog_page_size(): void
    {
        $html = view('prints.partials._responsive_invoice_print')->render();

        $this->assertStringContainsString('@page', $html);
        $this->assertStringContainsString('size: auto;', $html);
        $this->assertStringContainsString('table-layout: fixed', $html);
    }

    public function test_billing_invoice_footer_shows_software_line_and_printing_date_time(): void
    {
        $html = view('frontend.invoice.partials._footer-meta', [
            'footerMetaClass' => 'with-image',
            'footerPartsHtml' => '',
            'hasCustomFooterContent' => false,
            'footerFallbackLine' => 'Software By: www.toamed.com,Dhaka. Support: 01919-592638',
            'footerPrintedAt' => '13-Oct-2026 03:23:51 PM',
        ])->render();

        $this->assertStringContainsString('Software By: www.toamed.com,Dhaka. Support: 01919-592638', $html);
        $this->assertStringContainsString('Printing Date &amp; Time: 13-Oct-2026 03:23:51 PM', $html);
        $this->assertStringContainsString('footer-meta-wrap with-image', $html);
    }

    public function test_ipd_final_invoice_uses_its_design_assets_and_print_dialog_page_size(): void
    {
        $invoiceDesign = (new InvoiceDesign())->forceFill([
            'header_height' => 90,
            'footer_height' => 60,
            'footer_content_position' => 'above',
        ]);

        $html = view('prints.ipd.final-invoice', [
            'vm' => [
                'hospital' => [
                    'name' => 'Test Hospital',
                    'logo_url' => '',
                    'address' => '',
                    'phone' => '',
                    'email' => '',
                    'bin_vat_no' => '',
                    'tax_reg_no' => '',
                ],
                'design' => [
                    'header_image' => 'https://example.test/ipd-header.png',
                    'header_height' => 90,
                    'footer_image' => 'https://example.test/ipd-footer.png',
                    'footer_height' => 60,
                    'footer_content' => '<div>IPD final bill footer</div>',
                    'footer_content_position' => 'above',
                    'model' => $invoiceDesign,
                ],
                'invoice' => [
                    'invoice_no' => 'INV-1',
                    'ipd_no' => 'IPDN0001',
                    'uhid' => 'UHID-1',
                    'printed_at' => '01-01-2026 10:00 AM',
                    'admission_at' => '01-01-2026 10:00 AM',
                    'discharge_at' => '01-01-2026 10:00 AM',
                    'length_of_stay_label' => '1 day',
                ],
                'patient' => [
                    'name' => 'Test Patient',
                    'age' => '30',
                    'gender' => 'Other',
                    'mobile' => '01234567890',
                    'bed' => 'Bed 1',
                    'case' => 'Emergency',
                    'consultant_name' => 'Dr. Test',
                ],
                'payer' => ['payer_type' => 'SELF'],
                'package' => ['exists' => false],
                'dept_summary' => [],
                'lines' => [],
                'totals' => [
                    'gross_total' => 0,
                    'package_included_total' => 0,
                    'discount_total' => 0,
                    'taxable_total' => 0,
                    'tax_total' => 0,
                    'net_total' => 0,
                ],
                'payments' => [
                    'advance_total' => 0,
                    'insurance_payable' => 0,
                    'patient_final_payable' => 0,
                    'paid_total_excluding_advances' => 0,
                    'due_amount' => 0,
                    'refund_amount' => 0,
                ],
                'insurance' => [
                    'approved_amount' => 0,
                    'non_payable_amount' => 0,
                ],
            ],
        ])->render();

        $this->assertStringContainsString('https://example.test/ipd-header.png', $html);
        $this->assertStringContainsString('https://example.test/ipd-footer.png', $html);
        $this->assertStringContainsString('IPD final bill footer', $html);
        $this->assertStringContainsString('size: auto;', $html);
        $this->assertStringNotContainsString('size: A4 portrait;', $html);
    }
}
