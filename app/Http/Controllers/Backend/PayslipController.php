<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function download(Payslip $payslip)
    {
        $payslip->load('employee', 'salary');

        // If dompdf is available, generate PDF, otherwise render HTML view
        if (class_exists('\\Barryvdh\\DomPDF\\Facade\\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('backend.payslip', ['payslip' => $payslip]);
            return $pdf->download('payslip-' . $payslip->id . '.pdf');
        }

        return view('backend.payslip', ['payslip' => $payslip]);
    }
}
