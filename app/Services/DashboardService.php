<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Billing;
use App\Models\BillItem;
use App\Models\Expense;
use App\Models\OpdPatient;
use App\Models\IpdPatient;
use Carbon\Carbon;

class DashboardService
{
    public function todayDate()
    {
        return Carbon::today();
    }

    // ✔ Active Users
    public function countActiveUser()
    {
        return Admin::whereNull('deleted_at')
            ->where('status', 'Active')
            ->count();
    }

    // ✔ Inactive Users
    public function countInActiveUser()
    {
        return Admin::whereNull('deleted_at')
            ->where('status', 'Inactive')
            ->count();
    }

    // ✔ Pharmacy Income (Medicine)
    public function countPharmacyIncome()
    {
        return BillItem::where('category', 'Medicine')
            ->whereHas('billing', function ($q) {
                $q->whereDate('created_at', Carbon::today())
                  ->where('payment_status', '!=', 'Pending');
            })->sum('total_amount');
    }

    // ✔ Pathology Income
    public function countPathologyIncome()
    {
        return BillItem::where('category', 'Pathology')
            ->whereHas('billing', function ($q) {
                $q->whereDate('created_at', Carbon::today())
                  ->where('payment_status', '!=', 'Pending');
            })->sum('total_amount');
    }

    // ✔ Radiology Income
    public function countRadiologyIncome()
    {
        return BillItem::where('category', 'Radiology')
            ->whereHas('billing', function ($q) {
                $q->whereDate('created_at', Carbon::today())
                  ->where('payment_status', '!=', 'Pending');
            })->sum('total_amount');
    }

    // ✔ OPD Income (Today paid)
    public function countOpdIncome()
    {
        return OpdPatient::where('payment_status', '!=', 'Pending')
            ->whereDate('created_at', Carbon::today())
            ->sum('paid_amount');
    }

    // ✔ IPD Income (Credit limit)
    public function countIpdIncome()
    {
        return IpdPatient::whereDate('created_at', Carbon::today())
            ->sum('credit_limit');
    }

    // ✔ Pending Income (today's pending)
    
        public function countPendingIncome()
{
    return \App\Models\Billing::where('due_amount', '>', 0)
        ->sum('due_amount');
}

    // ✔ Total Discount
    public function countTotalDiscount()
    {
        $total = Billing::where('payment_status', '!=', 'Pending')
            ->whereDate('created_at', Carbon::today())
            ->sum('total');

        $payable = Billing::where('payment_status', '!=', 'Pending')
            ->whereDate('created_at', Carbon::today())
            ->sum('payable_amount');

        return $total - $payable;
    }

    // ✔ Net Income (Daily)
    public function countNetIncome()
    {
        $opd = OpdPatient::where('payment_status', '!=', 'Pending')
            ->whereDate('created_at', Carbon::today())
            ->sum('paid_amount');

        $ipd = IpdPatient::whereDate('created_at', Carbon::today())
            ->sum('credit_limit');

        $billing = Billing::where('payment_status', '!=', 'Pending')
            ->whereDate('created_at', Carbon::today())
            ->sum('paid_amt');

        $expense = Expense::whereDate('created_at', Carbon::today())->sum('amount');

        return ($opd + $ipd + $billing) - $expense;
    }

    // ✔ Daily Expense
    public function countExpense()
    {
        return Expense::whereDate('created_at', Carbon::today())->sum('amount');
    }
}
