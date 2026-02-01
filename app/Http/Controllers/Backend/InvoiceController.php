<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\InvoiceDesign;
use App\Models\WebSetting;
use Illuminate\Http\Request;
use App\Services\AdminService;
use App\Services\AppoinmentService;
use App\Services\BillingService;
use App\Services\MedicineInventoryService;
use App\Services\OpdPatientService;
use App\Services\PatientService;
use App\Services\ReferralPersonService;
use App\Traits\SystemTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Milon\Barcode\DNS1D;
use Milon\Barcode\DNS2D;
use Illuminate\Support\Str;


class InvoiceController extends Controller
{
    use SystemTrait;

    protected $billingService, $medicineInventoryService, $adminService, $patientService, $referrerService, $opdService, $appoinmentService;

    public function __construct(BillingService $billingService, MedicineInventoryService $medicineInventoryService, AdminService $adminService, PatientService $patientService, ReferralPersonService $referrerService, OpdPatientService $opdService, AppoinmentService $appoinmentService)
    {
        $this->billingService = $billingService;
        $this->medicineInventoryService = $medicineInventoryService;
        $this->adminService = $adminService;
        $this->patientService = $patientService;
        $this->referrerService = $referrerService;
        $this->opdService = $opdService;
        $this->appoinmentService = $appoinmentService;
    }

    public function downloadInvoice(Request $request)
    {
        $requestData = $request->all();
$billing = \App\Models\Billing::with('dueCollections')->findOrFail($requestData['id']);

// ===== FIXED INVOICE DATE & TIME (LIVE TIME OFF) =====
$invoiceDateTime = $billing->created_at->format('d-M-Y h:i:s A');
        
        $module = $requestData['module'] ?? '';

        $billItems = $this->filterBillItemsByModule($billing->billItems ?? [], $module);

        $patient = $this->patientService->find($billing->patient_id ?? '');

        $invoiceDesign = InvoiceDesign::where('status', 'Active')->where('module', $module)->first();

        if (!$invoiceDesign) {
            $invoiceDesign = [];
        }

        $headerImageBase64 = '';
        $footerImageBase64 = '';

        if ($invoiceDesign && $invoiceDesign->header_photo_path) {
            $relativePath = Str::after($invoiceDesign->header_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $headerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        if ($invoiceDesign && $invoiceDesign->footer_photo_path) {
            $relativePath = Str::after($invoiceDesign->footer_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $footerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        if ($billing) {
            $barcode = $this->generateBarcode($billing->bill_number ?? '');
        }

        $totals = $this->calculateFilteredTotals($billItems, $billing, $module);

        $data = [
            'billing' => $billing,
            'bill_number' => $billing->bill_number ?? '',
            'invoiceDateTime' => $invoiceDateTime,
            'patient_name' => $patient->name ?? 'N/A',
            'age' => $patient->age ?? 'N/A',
            'contact_no' => $billing->patient_mobile,
            'gender' => $billing->gender,
            'refd_by' => $billing->doctor_name ?? 'N/A',
            'bill_items' => $billItems,
            'total_amount' => $totals['total_amount'],
            'vat' => 0,
            'net_payable' => $totals['net_payable'],
            'discount' => $totals['discount'],
            'discount_type' => $billing['discount_type'],
            'extra_flat_discount' => $billing['extra_flat_discount'],
            'paid' => $totals['paid'],
            'due' => $totals['due'],
            'delivery_date' => $billing->delivery_date,
            'remarks' => $billing->remarks ?? '',
            'prepared_by' => $billing?->admin?->name ?? '',
            'amount_in_words' => $this->numberToWords($totals['net_payable']),
            'header_image' => $headerImageBase64,
            'footer_image' => $footerImageBase64,
            'footer_content' => $invoiceDesign->footer_content ?? '',
            'barcode' => $barcode,
            'module' => $module,
        ];

        $pdf = Pdf::loadView('frontend.invoice.pdf', $data);

        $pdf->setPaper('A4', 'portrait');

        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'dejavu sans',
            'dpi' => 96,
            'isPhpEnabled' => true,
            'isJavascriptEnabled' => true,
        ]);

        $filename = 'invoice_' . $billing->bill_number . '_' . $module . '.pdf';

        return $pdf->stream($filename, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
    }

    private function filterBillItemsByModule($billItems, $module)
    {
        if ($module === 'billing') {
            return $billItems;
        }

        $moduleMapping = [
            'pathology' => 'Pathology',
            'radiology' => 'Radiology',
            'pharmacy' => 'Medicine'
        ];

        $categoryFilter = $moduleMapping[$module] ?? null;

        if (!$categoryFilter) {
            return $billItems;
        }

        return $billItems->filter(function ($item) use ($categoryFilter) {
            return $item->category === $categoryFilter;
        });
    }

    private function calculateFilteredTotals($filteredItems, $billing, $module)
    {
        if ($module === 'billing') {
            return [
                'total_amount' => $billing->total,
                'discount' => $billing->discount,
                'net_payable' => $billing->payable_amount,
                'paid' => $billing->paid_amt,
                'due' => $billing->due_amount
            ];
        }

        $itemTotal = $filteredItems->sum('total_amount');
        $itemDiscount = $filteredItems->sum('discount');

        $proportionalDiscount = 0;
        if ($billing->total > 0 && $billing->discount > 0) {
            $proportionalDiscount = ($itemTotal / $billing->total) * $billing->discount;
        }

        $netPayable = $itemTotal - $itemDiscount - $proportionalDiscount;

        $proportionalPaid = 0;
        if ($billing->payable_amount > 0 && $billing->paid_amt > 0) {
            $proportionalPaid = ($netPayable / $billing->payable_amount) * $billing->paid_amt;
        }

        $due = $netPayable - $proportionalPaid;

        return [
            'total_amount' => $itemTotal,
            'discount' => $itemDiscount + $proportionalDiscount,
            'net_payable' => $netPayable,
            'paid' => $proportionalPaid,
            'due' => max(0, $due)
        ];
    }

    private function generateBarcode($billNumber)
    {
        $dns1d = new DNS1D();
        $barcode = $dns1d->getBarcodePNG($billNumber, 'C128', 3, 60);
        return 'data:image/png;base64,' . $barcode;
    }

    private function numberToWords($number)
    {
        $ones = [
            "",
            "One",
            "Two",
            "Three",
            "Four",
            "Five",
            "Six",
            "Seven",
            "Eight",
            "Nine",
            "Ten",
            "Eleven",
            "Twelve",
            "Thirteen",
            "Fourteen",
            "Fifteen",
            "Sixteen",
            "Seventeen",
            "Eighteen",
            "Nineteen"
        ];

        $tens = ["", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety"];

        $num = (int)$number;
        $words = "In Words: ";

        if ($num >= 100000) {
            $lakhs = (int)($num / 100000);
            $words .= $this->convertHundreds($lakhs, $ones, $tens) . " Lakh ";
            $num %= 100000;
        }

        if ($num >= 1000) {
            $thousands = (int)($num / 1000);
            $words .= $this->convertHundreds($thousands, $ones, $tens) . " Thousand ";
            $num %= 1000;
        }

        if ($num > 0) {
            $words .= $this->convertHundreds($num, $ones, $tens);
        }

        return trim($words) . " Only";
    }

    private function convertHundreds($num, $ones, $tens)
    {
        $words = "";

        if ($num >= 100) {
            $hundreds = (int)($num / 100);
            $words .= $ones[$hundreds] . " Hundred ";
            $num %= 100;
        }

        if ($num >= 20) {
            $ten = (int)($num / 10);
            $words .= $tens[$ten];
            $num %= 10;
            if ($num > 0) {
                $words .= " " . $ones[$num];
            }
        } elseif ($num > 0) {
            $words .= $ones[$num];
        }

        return $words;
    }

    public function downloadOpdInvoice(Request $request)
    {
        $requestData = $request->all();
        $opdPatient = $this->opdService->find($requestData['id']);

        $patient = $this->patientService->find($opdPatient->patient_id ?? '');
        $consultantDoctor = $this->adminService->find($opdPatient->consultant_doctor_id ?? '');

        // dd($patient, $opdPatient, $consultantDoctor, $consultantDoctor?->details?->qualification );

        $module = 'opd';

        $invoiceDesign = InvoiceDesign::where('status', 'Active')->where('module', $module)->first();

        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->whereNull('module')->first();
        }

        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->first();
        }

        $headerImageBase64 = '';
        $footerImageBase64 = '';

        if ($invoiceDesign && $invoiceDesign->header_photo_path) {
            $relativePath = Str::after($invoiceDesign->header_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $headerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        if ($invoiceDesign && $invoiceDesign->footer_photo_path) {
            $relativePath = Str::after($invoiceDesign->footer_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $footerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        $opdId = 'OPD' . str_pad($opdPatient->id, 3, '0', STR_PAD_LEFT);

        if ($opdPatient) {
            $barcode = $this->generateBarcode($opdId);
        }


        $age = 'N/A';
        if ($patient->dob) {
            $dob = new \DateTime($patient->dob);
            $now = new \DateTime();
            $ageYears = $now->diff($dob)->y;
            $age = $ageYears . ' Year (As Of Date ' . $now->format('d.m.Y') . ')';
        } elseif ($patient->age) {
            $age = $patient->age . ' Y';
        }

        $base_amount = $opdPatient->standard_charge;
        $discount = $opdPatient->discount ?? 0;
        $tax_percent = $opdPatient->tax ?? 0;
        $paid_amount = $opdPatient->paid_amount;

        $tax_amount = ($base_amount * $tax_percent) / 100;
        $discount_amount = ($base_amount * $discount) / 100;
        $net_amount = $base_amount + $tax_amount - $discount_amount;

        $data = [
            'opd_id' => $opdId,
            'opd_checkin_id' => 'OCID' . str_pad($opdPatient->id, 2, '0', STR_PAD_LEFT),
            'appointment_date' => \Carbon\Carbon::parse($opdPatient->appointment_date)->format('d-m-Y h:i A'),
            'patient_name' => $patient->name ?? 'N/A',
            'age' => $age,
            'gender' => $patient->gender ?? 'N/A',
            'blood_group' => $patient->blood_group ?? '',
            'known_allergies' => $opdPatient->allergies ?? '',
            'address' => $patient->address ?? '',
            'consultant_doctor' => $consultantDoctor->name ?? 'N/A',
            'consultant_qualification' => $consultantDoctor?->details?->qualification ?? '',
            'department' => $opdPatient->consultation_type ?? '',

            // Payment details
            'description' => $opdPatient?->chargeType?->name ?? '',
            'tax_percent' => $opdPatient->tax ?? 0,
            'amount' => $opdPatient->standard_charge ?? 0,
            'net_amount' => $net_amount ?? 0,
            'discount' => $opdPatient->discount ?? 0,
            'discount_amount' => $discount_amount ?? 0,
            'tax_amount' => $tax_amount ?? 0,
            'total_amount' => $opdPatient->amount ?? 0,
            'paid_amount' => $paid_amount ?? 0,
            'balance_amount' => $opdPatient->balance_amount ?? 0,

            'header_image' => $headerImageBase64,
            'footer_image' => $footerImageBase64,
            'footer_content' => $invoiceDesign->footer_content ?? '',
            'barcode' => $barcode,
            'clinic_address' => 'Daulatur Master Para, Daulatur Kushita Mobile: 01796-302512',
        ];

        $pdf = Pdf::loadView('frontend.invoice.opd-pdf', $data);

        return $pdf->stream('opd_invoice_' . $opdId . '.pdf');
    }

    public function downloadAppointmentInvoice(Request $request)
    {
        $requestData = $request->all();

        $appointment = $this->appoinmentService->find($requestData['id']);
        $patient = $this->patientService->find($appointment->patient_id ?? '');
        $doctor = $this->adminService->find($appointment->doctor_id ?? '');

        // dd($patient, $doctor);

        $module = 'appointment';

        $invoiceDesign = InvoiceDesign::where('status', 'Active')->where('module', $module)->first();

        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->whereNull('module')->first();
        }

        if (!$invoiceDesign) {
            $invoiceDesign = InvoiceDesign::where('status', 'Active')->first();
        }

        $headerImageBase64 = '';
        $footerImageBase64 = '';

        // Process header image
        if ($invoiceDesign && $invoiceDesign->header_photo_path) {
            $relativePath = Str::after($invoiceDesign->header_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $headerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        // Process footer image
        if ($invoiceDesign && $invoiceDesign->footer_photo_path) {
            $relativePath = Str::after($invoiceDesign->footer_photo_path, '/storage/');
            $storagePath = storage_path('app/public/' . $relativePath);

            if (file_exists($storagePath)) {
                $footerImageBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($storagePath));
            }
        }

        $appointmentId = 'APPN' . str_pad($appointment->id, 3, '0', STR_PAD_LEFT);

        $data = [
            'appointment' => $appointment,
            'patient' => $patient,
            'doctor' => $doctor,
            'header_image' => $headerImageBase64,
            'footer_image' => $footerImageBase64,
            'footer_content' => $invoiceDesign->footer_content ?? '',
        ];

        $pdf = Pdf::loadView('frontend.invoice.appointment-pdf', $data);

        return $pdf->stream('appointment_invoice_' . $appointmentId . '.pdf');
    }
}
