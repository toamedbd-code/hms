<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index()
    {
        $dashboardData = [
            'activeUsers'        => $this->dashboardService->countActiveUser(),
            'inActiveUsers'      => $this->dashboardService->countInActiveUser(),

            'pharmacyIncome'     => $this->dashboardService->countPharmacyIncome(),
            'pathologyIncome'    => $this->dashboardService->countPathologyIncome(),
            'radiologyIncome'    => $this->dashboardService->countRadiologyIncome(),

            'opdIncome'          => $this->dashboardService->countOpdIncome(),
            'ipdIncome'          => $this->dashboardService->countIpdIncome(),
            'pendingIncome'      => $this->dashboardService->countPendingIncome(),

            'totalDiscountAmount'=> $this->dashboardService->countTotalDiscount(),
            'expenses'           => $this->dashboardService->countExpense(),
            'netIncome'          => $this->dashboardService->countNetIncome(),
        ];

        return Inertia::render('Backend/Dashboard', [
            'pageTitle' => 'Dashboard',
            'dashboardData' => $dashboardData,
        ]);
    }
}
