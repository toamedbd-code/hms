<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Gate;
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
        $admin = auth()->guard('admin')->user();
        $detail = $admin?->details;

        $can = static fn (string $permission) => $admin ? Gate::forUser($admin)->check($permission) : false;

        $dashboardCardPermissions = [
            'opdIncome' => $can('dashboard-card-opd-income'),
            'ipdIncome' => $can('dashboard-card-ipd-income'),
            'pharmacyIncome' => $can('dashboard-card-pharmacy-income'),
            'pathologyIncome' => $can('dashboard-card-pathology-income'),
            'radiologyIncome' => $can('dashboard-card-radiology-income'),
            'bloodBankIncome' => $can('dashboard-card-blood-bank-income'),
            'expenses' => $can('dashboard-card-expenses'),
            'pendingIncome' => $can('dashboard-card-pending-income'),
            'netIncome' => $can('dashboard-card-net-income'),
            'totalDiscountAmount' => $can('dashboard-card-total-discount'),
            'expiredMedicines' => $can('dashboard-card-expired-medicines'),
            'expiringMedicines' => $can('dashboard-card-expiring-medicines'),
        ];

        if (!in_array(true, $dashboardCardPermissions, true) && $can('dashboard')) {
            $dashboardCardPermissions = array_map(static fn () => true, $dashboardCardPermissions);
        }

        $filterType = $detail?->dashboard_filter_type ?? 'daily';
        $filterFrom = $detail?->dashboard_filter_from;
        $filterTo = $detail?->dashboard_filter_to;

        $filter = $this->dashboardService->resolveDashboardFilter($filterType, $filterFrom, $filterTo);
        $dbRange = $filter['dbRange'];
        $dateRange = $filter['appRange'];

        $dashboardData = [
            'activeUsers'        => $this->dashboardService->countActiveUser(),
            'inActiveUsers'      => $this->dashboardService->countInActiveUser(),

            'pharmacyIncome'     => $this->dashboardService->countPharmacyIncome($dbRange, $dateRange),
            'pathologyIncome'    => $this->dashboardService->countPathologyIncome($dbRange, $dateRange),
            'radiologyIncome'    => $this->dashboardService->countRadiologyIncome($dbRange, $dateRange),
            'bloodBankIncome'    => $this->dashboardService->countBloodBankIncome($dbRange, $dateRange),

            'opdIncome'          => $this->dashboardService->countOpdIncome($dateRange),
            'ipdIncome'          => $this->dashboardService->countIpdIncome($dbRange),
            'pendingIncome'      => $this->dashboardService->countPendingIncome($dbRange, $dateRange),
            'totalIncome'        => $this->dashboardService->countTotalIncome($dbRange, $dateRange),

            'totalDiscountAmount'=> $this->dashboardService->countTotalDiscount($dbRange),
            'expenses'           => $this->dashboardService->countExpense($dateRange),
            'netIncome'          => $this->dashboardService->countNetIncome($dbRange, $dateRange),
        ];

        return Inertia::render('Backend/Dashboard', [
            'pageTitle' => 'Dashboard',
            'dashboardData' => $dashboardData,
            'dashboardCardPermissions' => $dashboardCardPermissions,
            'dashboardFilter' => [
                'type' => $filter['type'],
                'from' => $filter['from'],
                'to' => $filter['to'],
            ],
        ]);
    }
}
