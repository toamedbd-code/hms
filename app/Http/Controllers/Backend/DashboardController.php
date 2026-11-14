<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Test;
use App\Models\TestCategory;
use App\Services\DashboardService;
use App\Services\TestCategoryService;
use App\Models\MainCategory;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected $dashboardService;
    protected $testCategoryService;

    public function __construct(DashboardService $dashboardService, TestCategoryService $testCategoryService)
    {
        $this->dashboardService = $dashboardService;
        $this->testCategoryService = $testCategoryService;
    }

    public function index()
    {
        $admin = auth()->guard('admin')->user();
        $detail = $admin?->details;

        $can = static fn (string $permission) => $admin ? Gate::forUser($admin)->check($permission) : false;

        $dashboardCardPermissions = [
            'opdIncome' => $can('dashboard-card-opd-income'),
            'doctorVisitAppointment' => $can('dashboard-card-doctor-visit-appointment'),
            'ipdIncome' => $can('dashboard-card-ipd-income'),
            'pharmacyIncome' => $can('dashboard-card-pharmacy-income') && user_has_module($admin, 'pharmacy'),
            'pathologyIncome' => $can('dashboard-card-pathology-income'),
            'radiologyIncome' => $can('dashboard-card-radiology-income'),
            'bloodBankIncome' => $can('dashboard-card-blood-bank-income'),
            'expenses' => $can('dashboard-card-expenses'),
            'pendingIncome' => $can('dashboard-card-pending-income'),
            'referralCommission' => $can('dashboard-card-referral-commission'),
            'netIncome' => $can('dashboard-card-net-income'),
            'totalIncome' => $can('dashboard-card-total-income'),
            'refunds' => $can('dashboard-card-refunds'),
            'totalDiscountAmount' => $can('dashboard-card-total-discount'),
            'disposableIncome' => $can('dashboard-card-disposable-income'),
            'expiredMedicines' => $can('dashboard-card-expired-medicines') && user_has_module($admin, 'pharmacy'),
            'expiringMedicines' => $can('dashboard-card-expiring-medicines') && user_has_module($admin, 'pharmacy'),
            'productReturns' => $can('dashboard-card-product-returns')
                && user_has_module($admin, 'pharmacy'),
            // chart permissions
            'chartIncomeByDepartment' => $can('dashboard-chart-income-by-department'),
            'chartIncomeDistribution' => $can('dashboard-chart-income-distribution'),
        ];

        if (user_dashboard_department_slug($admin) === 'pharmacy' && user_has_module($admin, 'pharmacy')) {
            $filterType = $detail?->dashboard_filter_type ?? 'daily';
            $filter = $this->dashboardService->resolveDashboardFilter(
                $filterType,
                $detail?->dashboard_filter_from,
                $detail?->dashboard_filter_to
            );

            return $this->renderPharmacyDashboard($admin, $filter);
        }

        $activeCategories = collect($this->testCategoryService->activeNames())
            ->map(fn ($name) => (object) ['name' => $name]);
        $activeMainCategories = MainCategory::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'name']);
        $diagnosticTests = Test::query()
            ->where('status', 'Active')
            ->whereRaw('LOWER(category_type) IN (?, ?)', ['pathology', 'radiology'])
            ->get(['main_category_id', 'test_category_id', 'category_type']);

        $activeCategories
            ->each(function ($category) use (&$dashboardCardPermissions, $can) {
                $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($category->name))), '-');
                if ($slug !== '') {
                    $dashboardCardPermissions['custom-category-' . $slug] = $can('dashboard-card-' . $slug . '-income');
                }
            });

        $activeMainCategories
            ->each(function ($category) use (&$dashboardCardPermissions, $can) {
                $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($category->name))), '-');
                if ($slug !== '') {
                    $dashboardCardPermissions['main-category-' . $slug] = $can('dashboard-main-category-' . $slug . '-income');
                }
            });

        $hasIncomeChartPermission = (
            $dashboardCardPermissions['chartIncomeByDepartment']
            || $dashboardCardPermissions['chartIncomeDistribution']
        );

        $mainCategoryIdsByType = ['pathology' => [], 'radiology' => []];
        $allMainCategoryIdsByType = ['pathology' => [], 'radiology' => []];
        $selectedTestCategoryIds = collect();
        $testCategoriesById = TestCategory::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->get(['id', 'name', 'parent_id'])
            ->keyBy('id');

        $selectedCategoryNames = $activeCategories
            ->filter(function ($category) use ($dashboardCardPermissions) {
                $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($category->name))), '-');
                return $slug !== '' && ($dashboardCardPermissions['custom-category-' . $slug] ?? false);
            })
            ->map(fn ($category) => strtolower(trim($category->name)));

        foreach ($testCategoriesById as $category) {
            if (!$selectedCategoryNames->contains(strtolower(trim($category->name)))) {
                continue;
            }

            $pendingIds = collect([(int) $category->id]);
            $visitedIds = collect();
            while ($pendingIds->isNotEmpty()) {
                $newIds = $pendingIds->diff($visitedIds)->values();
                if ($newIds->isEmpty()) {
                    break;
                }

                $visitedIds = $visitedIds->merge($newIds)->unique()->values();
                $selectedTestCategoryIds = $selectedTestCategoryIds->merge($newIds);
                $pendingIds = $testCategoriesById
                    ->whereIn('parent_id', $newIds->all())
                    ->pluck('id');
            }
        }

        $selectedTestIdsByType = ['pathology' => [], 'radiology' => []];
        foreach ($activeMainCategories as $category) {
            $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($category->name))), '-');
            $categoryTests = $diagnosticTests->where('main_category_id', $category->id);
            foreach ($categoryTests as $test) {
                $type = strtolower(trim((string) $test->category_type));
                if (!array_key_exists($type, $mainCategoryIdsByType)) {
                    continue;
                }

                $allMainCategoryIdsByType[$type][$category->id] = (int) $category->id;
                $hasMainCategoryPermission = $slug !== ''
                    && ($dashboardCardPermissions['main-category-' . $slug] ?? false);
                if ($hasMainCategoryPermission) {
                    $mainCategoryIdsByType[$type][$category->id] = (int) $category->id;
                }
                if (($dashboardCardPermissions[$type . 'Income'] ?? false)
                    || $hasMainCategoryPermission
                    || $selectedTestCategoryIds->contains((int) $test->test_category_id)) {
                    $selectedTestIdsByType[$type][] = (int) $test->id;
                }
            }
        }

        $selectedTestCategoryIds = $selectedTestCategoryIds->unique()->values();
        foreach ($diagnosticTests as $test) {
            $type = strtolower(trim((string) $test->category_type));
            if (!array_key_exists($type, $selectedTestIdsByType)) {
                continue;
            }
            if (($dashboardCardPermissions[$type . 'Income'] ?? false)
                || ($test->test_category_id && $selectedTestCategoryIds->contains((int) $test->test_category_id))) {
                $selectedTestIdsByType[$type][] = (int) $test->id;
            }
        }

        $selectedTestIdsByType = array_map(static fn ($testIds) => array_values(array_unique($testIds)), $selectedTestIdsByType);
        $selectedMainCategoryIds = collect($mainCategoryIdsByType)
            ->flatten()
            ->unique()
            ->values();
        $suppressedCategoryNames = collect();
        $suppressedTestCategoryIds = $diagnosticTests
            ->whereIn('main_category_id', $selectedMainCategoryIds->all())
            ->pluck('test_category_id')
            ->filter()
            ->unique()
            ->values();
        foreach ($suppressedTestCategoryIds as $categoryId) {
            $category = $testCategoriesById->get($categoryId);
            $visitedCategoryIds = [];
            while ($category && !isset($visitedCategoryIds[$category->id])) {
                $visitedCategoryIds[$category->id] = true;
                $suppressedCategoryNames->push(strtolower(trim($category->name)));
                $category = $category->parent_id ? $testCategoriesById->get($category->parent_id) : null;
            }
        }
        $suppressedCategoryNames = $suppressedCategoryNames->unique()->values();
        $diagnosticTypesByCategoryName = [];
        foreach ($diagnosticTests as $test) {
            $type = strtolower(trim((string) $test->category_type));
            $category = $testCategoriesById->get($test->test_category_id);
            $visitedCategoryIds = [];
            while ($category && !isset($visitedCategoryIds[$category->id])) {
                $visitedCategoryIds[$category->id] = true;
                $name = strtolower(trim($category->name));
                $diagnosticTypesByCategoryName[$name][$type] = true;
                $category = $category->parent_id ? $testCategoriesById->get($category->parent_id) : null;
            }
        }
        $diagnosticTypesByCategoryName = array_map(
            static fn (array $types) => count($types) === 1 ? array_key_first($types) : null,
            $diagnosticTypesByCategoryName
        );

        $hasPathologyIncomePermission = $dashboardCardPermissions['pathologyIncome'];
        $hasRadiologyIncomePermission = $dashboardCardPermissions['radiologyIncome'];
        $filterType = $detail?->dashboard_filter_type ?? 'daily';
        $filterFrom = $detail?->dashboard_filter_from;
        $filterTo = $detail?->dashboard_filter_to;

        $filter = $this->dashboardService->resolveDashboardFilter($filterType, $filterFrom, $filterTo);
        $dbRange = $filter['dbRange'];
        $dateRange = $filter['appRange'];
        $visibleMainCategoryNames = $activeMainCategories
            ->filter(function ($category) use ($dashboardCardPermissions) {
                $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($category->name))), '-');
                return $slug !== '' && ($dashboardCardPermissions['main-category-' . $slug] ?? false);
            })
            ->map(fn ($category) => strtolower(trim($category->name)))
            ->unique()
            ->values();
        $visibleCategoryNames = $activeCategories
            ->filter(function ($category) use ($dashboardCardPermissions, $suppressedCategoryNames, $visibleMainCategoryNames) {
                $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($category->name))), '-');
                $name = strtolower(trim($category->name));
                return $slug !== ''
                    && !$suppressedCategoryNames->contains($name)
                    && !$visibleMainCategoryNames->contains($name)
                    && ($dashboardCardPermissions['custom-category-' . $slug] ?? false);
            })
            ->pluck('name')
            ->values()
            ->all();

        $categoryIncomes = $activeCategories
            ->filter(function ($category) use ($dashboardCardPermissions, $suppressedCategoryNames, $visibleMainCategoryNames) {
                $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($category->name))), '-');
                $name = strtolower(trim($category->name));
                return $slug !== ''
                    && !$suppressedCategoryNames->contains($name)
                    && !$visibleMainCategoryNames->contains($name)
                    && !in_array($name, ['pathology', 'radiology'], true)
                    && ($dashboardCardPermissions['custom-category-' . $slug] ?? false);
            })
            ->map(fn ($category) => [
                'name' => $category->name,
                'amount' => $this->dashboardService->countCategoryIncome($category->name, $dbRange, $visibleCategoryNames),
                'categoryType' => $diagnosticTypesByCategoryName[strtolower(trim($category->name))] ?? null,
                'permission' => 'dashboard-card-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($category->name))), '-') . '-income',
            ])
            ->values()
            ->all();

        $mainCategoryIncomes = $activeMainCategories
            ->filter(function ($category) use ($dashboardCardPermissions) {
                $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($category->name))), '-');
                return $slug !== '' && ($dashboardCardPermissions['main-category-' . $slug] ?? false);
            })
            ->map(fn ($category) => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'amount' => $this->dashboardService->countMainCategoryIncome((int) $category->id, $dbRange),
            ])
            ->values()
            ->all();

        $pathologyIncomeFromMainCategories = $selectedTestIdsByType['pathology'] !== []
            || (($hasPathologyIncomePermission || $hasIncomeChartPermission) && $allMainCategoryIdsByType['pathology'] !== []);
        $radiologyIncomeFromMainCategories = $selectedTestIdsByType['radiology'] !== []
            || (($hasRadiologyIncomePermission || $hasIncomeChartPermission) && $allMainCategoryIdsByType['radiology'] !== []);
        $netIncome = null;
        if ($dashboardCardPermissions['netIncome'] || $hasIncomeChartPermission) {
            $netIncome = $this->dashboardService->countNetIncome($dbRange, $dateRange);
        }

        $dashboardData = [
            'activeUsers'        => $this->dashboardService->countActiveUser(),
            'inActiveUsers'      => $this->dashboardService->countInActiveUser(),
            'categoryIncomes' => $categoryIncomes,
            'mainCategoryIncomes' => $mainCategoryIncomes,
        ];

        if ($dashboardCardPermissions['pharmacyIncome'] || $hasIncomeChartPermission) {
            $dashboardData['pharmacyIncome'] = $this->dashboardService->countPharmacyIncome($dbRange, $dateRange);
        }
        if ($dashboardCardPermissions['pathologyIncome'] || $hasIncomeChartPermission) {
            if ($pathologyIncomeFromMainCategories) {
                $dashboardData['pathologyIncome'] = ($hasPathologyIncomePermission || $hasIncomeChartPermission)
                    && $allMainCategoryIdsByType['pathology'] !== []
                    ? $this->dashboardService->countMainCategoryTypeIncome('Pathology', $dbRange)
                    : $this->dashboardService->countDiagnosticIncome('Pathology', $selectedTestIdsByType['pathology'], $dbRange);
            } else {
                $pathologySummary = $this->dashboardService->getPathologyIncomeSummary($dbRange);
                $dashboardData['pathologyIncome'] = $pathologySummary['net'];
            }
            if ($hasPathologyIncomePermission) {
                $pathologySummary ??= $this->dashboardService->getPathologyIncomeSummary($dbRange);
                $dashboardData['pathologyTotalAmount'] = $pathologySummary['total'];
                $dashboardData['pathologyDiscountAmount'] = $pathologySummary['discount'];
                $dashboardData['pathologyNetIncome'] = $pathologySummary['net'];
            }
        }
        if ($dashboardCardPermissions['radiologyIncome'] || $hasIncomeChartPermission) {
            if ($radiologyIncomeFromMainCategories) {
                $dashboardData['radiologyIncome'] = ($hasRadiologyIncomePermission || $hasIncomeChartPermission)
                    && $allMainCategoryIdsByType['radiology'] !== []
                    ? $this->dashboardService->countMainCategoryTypeIncome('Radiology', $dbRange)
                    : $this->dashboardService->countDiagnosticIncome('Radiology', $selectedTestIdsByType['radiology'], $dbRange);
            } else {
                $dashboardData['radiologyIncome'] = $this->dashboardService->countRadiologyIncome($dbRange, $dateRange);
            }
        }
        if (isset($dashboardData['pathologyIncome'], $dashboardData['radiologyIncome'])) {
            [
                $dashboardData['pathologyIncome'],
                $dashboardData['radiologyIncome'],
            ] = $this->dashboardService->addDiscountRoundingToDiagnosticIncomes(
                (float) $dashboardData['pathologyIncome'],
                (float) $dashboardData['radiologyIncome'],
                $dbRange
            );
        }
        if ($dashboardCardPermissions['bloodBankIncome']) {
            $dashboardData['bloodBankIncome'] = $this->dashboardService->countBloodBankIncome($dbRange, $dateRange);
        }
        if ($dashboardCardPermissions['opdIncome'] || $hasIncomeChartPermission) {
            $dashboardData['opdIncome'] = $this->dashboardService->countOpdIncome($dateRange);
        }
        if ($dashboardCardPermissions['doctorVisitAppointment']) {
            $doctorVisitAppointmentSummary = $this->dashboardService->getDoctorVisitAppointmentSummary($dateRange);
            $dashboardData['doctorVisitAppointment'] = $doctorVisitAppointmentSummary['total'];
            $dashboardData['doctorVisitAppointmentByDoctor'] = $doctorVisitAppointmentSummary['byDoctor'];
        }
        if ($dashboardCardPermissions['ipdIncome'] || $hasIncomeChartPermission) {
            $dashboardData['ipdIncome'] = $this->dashboardService->countIpdIncome($dbRange);
        }
        if ($dashboardCardPermissions['disposableIncome'] || $hasIncomeChartPermission) {
            $dashboardData['disposableIncome'] = $this->dashboardService->countDisposableIncome($dbRange, $dateRange);
        }
        if ($dashboardCardPermissions['pendingIncome'] || $hasIncomeChartPermission) {
            $dashboardData['pendingIncome'] = $this->dashboardService->countPendingIncome($dbRange, $dateRange);
        }
        if ($dashboardCardPermissions['referralCommission']) {
            $dashboardData['referralCommission'] = $this->dashboardService->countReferralCommissionPending($dbRange);
            $dashboardData['referralCommissionPayeeCount'] = $this->dashboardService->countReferralCommissionPendingPayees($dbRange);
        }
        if ($dashboardCardPermissions['productReturns']) {
            $dashboardData['productReturns'] = $this->dashboardService->countProductReturns($dateRange);
        }
        if ($dashboardCardPermissions['totalIncome']) {
            $dashboardData['totalIncome'] = $this->dashboardService->countTotalIncome($dbRange, $dateRange);
        }
        if ($dashboardCardPermissions['totalDiscountAmount']) {
            $dashboardData['totalDiscountAmount'] = $this->dashboardService->countTotalDiscount($dbRange);
        }
        if ($dashboardCardPermissions['expenses'] || $hasIncomeChartPermission) {
            $dashboardData['expenses'] = $this->dashboardService->countExpense($dateRange);
        }
        if ($netIncome !== null) {
            $dashboardData['netIncome'] = $netIncome;
        }
        if ($dashboardCardPermissions['refunds']) {
            $dashboardData['refunds'] = $this->dashboardService->countRefunds($dbRange, $dateRange);
        }

        $chartIncomeItems = [];
        $fixedIncomeFields = [
            'opdIncome' => ['name' => 'OPD Income', 'permission' => 'opdIncome'],
            'ipdIncome' => ['name' => 'IPD Income', 'permission' => 'ipdIncome'],
            'pharmacyIncome' => ['name' => 'Pharmacy Income', 'permission' => 'pharmacyIncome'],
            'pathologyIncome' => ['name' => 'Pathology Income', 'permission' => 'pathologyIncome'],
            'radiologyIncome' => ['name' => 'Radiology Income', 'permission' => 'radiologyIncome'],
            'bloodBankIncome' => ['name' => 'Blood Bank Income', 'permission' => 'bloodBankIncome'],
            'disposableIncome' => ['name' => 'Disposable Income', 'permission' => 'disposableIncome'],
            'doctorVisitAppointment' => ['name' => 'Total Doctor Visit Appointment', 'permission' => 'doctorVisitAppointment'],
        ];
        $addVisibleIncomeItem = static function (string $name, float $amount) use (&$chartIncomeItems): void {
            $normalizedName = strtolower(trim($name));
            foreach ($chartIncomeItems as $item) {
                if (strtolower(trim($item['name'])) === $normalizedName) {
                    return;
                }
            }
            $chartIncomeItems[] = ['name' => $name, 'amount' => $amount];
        };
        foreach ($fixedIncomeFields as $key => $field) {
            if (($dashboardCardPermissions[$field['permission']] ?? false) && array_key_exists($key, $dashboardData)) {
                $addVisibleIncomeItem($field['name'], (float) $dashboardData[$key]);
            }
        }
        foreach ($categoryIncomes as $categoryIncome) {
            $addVisibleIncomeItem(
                trim((string) $categoryIncome['name']) . ' Income',
                (float) $categoryIncome['amount']
            );
        }
        foreach ($mainCategoryIncomes as $categoryIncome) {
            $addVisibleIncomeItem(
                trim((string) $categoryIncome['name']) . ' Income',
                (float) $categoryIncome['amount']
            );
        }

        $chartSupplementaryItems = [];
        foreach ([
            'pendingIncome' => ['name' => 'Pending Income', 'permission' => 'pendingIncome'],
            'expenses' => ['name' => 'Expenses', 'permission' => 'expenses'],
            'refunds' => ['name' => 'Total Refunds', 'permission' => 'refunds'],
            'netIncome' => ['name' => 'Total Net Income', 'permission' => 'netIncome'],
        ] as $key => $field) {
            if (($dashboardCardPermissions[$field['permission']] ?? false) && array_key_exists($key, $dashboardData)) {
                $chartSupplementaryItems[] = [
                    'name' => $field['name'],
                    'amount' => (float) $dashboardData[$key],
                ];
            }
        }
        $dashboardData['chartIncomeItems'] = $chartIncomeItems;
        $dashboardData['chartSupplementaryItems'] = $chartSupplementaryItems;

        // Debug logging removed to reduce log noise in production

        return Inertia::render('Backend/Dashboard', [
            'pageTitle' => 'Dashboard',
            'dashboardData' => $dashboardData,
            'dashboardCardPermissions' => $dashboardCardPermissions,
            'departmentScope' => null,
            'dashboardFilter' => [
                'type' => $filter['type'],
                'from' => $filter['from'],
                'to' => $filter['to'],
            ],
        ]);
    }

    private function renderPharmacyDashboard($admin, array $filter)
    {
        $cardPermissions = [
            'pharmacyIncome' => 'dashboard-card-pharmacy-income',
            'expenses' => 'dashboard-card-expenses',
            'pendingIncome' => 'dashboard-card-pending-income',
            'netIncome' => 'dashboard-card-net-income',
            'refunds' => 'dashboard-card-refunds',
            'totalDiscountAmount' => 'dashboard-card-total-discount',
            'productReturns' => 'dashboard-card-product-returns',
        ];
        $dashboardCardPermissions = array_fill_keys([
            'opdIncome',
            'doctorVisitAppointment',
            'ipdIncome',
            'pharmacyIncome',
            'pathologyIncome',
            'radiologyIncome',
            'bloodBankIncome',
            'expenses',
            'pendingIncome',
            'referralCommission',
            'netIncome',
            'totalIncome',
            'refunds',
            'totalDiscountAmount',
            'disposableIncome',
            'expiredMedicines',
            'expiringMedicines',
            'productReturns',
            'chartIncomeByDepartment',
            'chartIncomeDistribution',
        ], false);
        foreach ($cardPermissions as $key => $permission) {
            $dashboardCardPermissions[$key] = Gate::forUser($admin)->check($permission);
        }

        $departmentId = $admin?->details?->department_id;
        if (!$departmentId) {
            $departmentId = Department::query()
                ->whereRaw('LOWER(name) LIKE ?', ['%pharmacy%'])
                ->value('id');
        }

        $summary = $this->dashboardService->getDepartmentDashboardSummary(
            'pharmacy',
            $departmentId ? (int) $departmentId : null,
            $filter['dbRange'],
            $filter['appRange']
        );

        $dashboardData = [];
        foreach ([
            'pharmacyIncome' => 'income',
            'expenses' => 'expenses',
            'pendingIncome' => 'pendingIncome',
            'netIncome' => 'netIncome',
            'refunds' => 'refunds',
            'totalDiscountAmount' => 'totalDiscountAmount',
            'productReturns' => 'refunds',
        ] as $key => $summaryKey) {
            if ($dashboardCardPermissions[$key]) {
                $dashboardData[$key] = $summary[$summaryKey] ?? 0;
            }
        }

        return Inertia::render('Backend/Dashboard', [
            'pageTitle' => 'Dashboard',
            'dashboardData' => $dashboardData,
            'dashboardCardPermissions' => $dashboardCardPermissions,
            'departmentScope' => 'pharmacy',
            'dashboardFilter' => [
                'type' => $filter['type'],
                'from' => $filter['from'],
                'to' => $filter['to'],
            ],
        ]);
    }
}
