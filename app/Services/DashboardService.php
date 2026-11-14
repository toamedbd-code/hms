<?php

namespace App\Services;
use App\Models\Admin;
use App\Models\Billing;
use App\Models\BillItem;
use Illuminate\Support\Facades\Schema;
use App\Models\DueCollection;
use App\Models\Expense;
use App\Models\ProductReturn;
use App\Models\Referral;
use App\Models\OpdPatient;
use App\Models\Appoinment;
use App\Models\IpdPatient;
use App\Models\MainCategory;
use App\Models\PharmacyBill;
use App\Models\Test;
use App\Models\TestCategory;
use Carbon\Carbon;
use App\Services\ReportAccountingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardService
{
    protected ReportAccountingService $accountingService;
    private array $billAccountingTotalsByRange = [];

    public function __construct(ReportAccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function todayDate()
    {
        return Carbon::today();
    }

    public function getDate($day = 'today')
    {
        if ($day === 'yesterday') {
            return Carbon::yesterday();
        }
        return Carbon::today();
    }

    public function getDateRange($day = 'today')
    {
        $appTimezone = config('app.timezone');
        $dbTimezone = config('database.connections.mysql.timezone_offset') ?: '+00:00';

        $date = $day === 'yesterday'
            ? Carbon::now($appTimezone)->subDay()
            : Carbon::now($appTimezone);

        $start = $date->copy()->startOfDay()->setTimezone($dbTimezone);
        $end = $date->copy()->endOfDay()->setTimezone($dbTimezone);

        return [$start, $end];
    }

    public function resolveDashboardFilter(?string $filterType, ?string $fromDate, ?string $toDate)
    {
        $allowedTypes = ['daily', 'monthly', 'yearly', 'custom'];
        $type = in_array($filterType, $allowedTypes, true) ? $filterType : 'daily';

        $appTimezone = config('app.timezone');
        $dbTimezone = config('database.connections.mysql.timezone_offset') ?: '+00:00';
        $now = Carbon::now($appTimezone);

        switch ($type) {
            case 'monthly':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                break;
            case 'yearly':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                break;
            case 'custom':
                if ($fromDate && $toDate) {
                    $start = Carbon::parse($fromDate, $appTimezone)->startOfDay();
                    $end = Carbon::parse($toDate, $appTimezone)->endOfDay();
                    break;
                }
                $type = 'daily';
            default:
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
        }

        $appRange = [$start->copy(), $end->copy()];
        $dbRange = [$start->copy()->setTimezone($dbTimezone), $end->copy()->setTimezone($dbTimezone)];

        return [
            'type' => $type,
            'appRange' => $appRange,
            'dbRange' => $dbRange,
            'from' => $appRange[0]->toDateString(),
            'to' => $appRange[1]->toDateString(),
        ];
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

    // ✔ Pharmacy Income (Pharmacy module only) - collected amounts
    public function countPharmacyIncome(array $dbRange, array $dateRange)
    {
        // Identify pharmacy bills in the selected date range, then locate the
        // corresponding Billing records (by bill number) and sum payments + due-collections
        // recorded against those billings. This matches the report rows the user sees.
        $pharmacyRows = PharmacyBill::query()
            ->where('status', 'Active')
            // Use DB-range (timezone-aware) when querying pharmacy bill dates
            ->whereBetween('date', [
                $dbRange[0]->toDateString(),
                $dbRange[1]->toDateString(),
            ])
            // fetch safe fields only; amount fields may not exist on all schemas
            ->get(['id', 'bill_no', 'date', 'net_amount']);

        if ($pharmacyRows->isEmpty()) {
            return 0;
        }

        $billNos = $pharmacyRows->pluck('bill_no')->filter()->unique()->values()->toArray();
        if (empty($billNos)) {
            return 0;
        }

        $billingIdsByBillNumber = Billing::query()
            ->whereIn('bill_number', $billNos)
            ->pluck('id', 'bill_number');
        $billingIds = $billingIdsByBillNumber->values()->unique()->all();

        $returnsQuery = ProductReturn::query()
            ->where('return_type', 'customer')
            ->whereIn('status', ['approved', 'processed'])
            ->whereBetween('return_date', [
                $dateRange[0]->toDateString(),
                $dateRange[1]->toDateString(),
            ])
            ->where(function ($query) use ($billNos, $billingIds) {
                $query->whereIn('source_bill_no', $billNos);
                if ($billingIds !== []) {
                    $query->orWhereIn('billing_id', $billingIds);
                }
            });
        $returnDeduction = (float) $returnsQuery->sum('total_amount');

        // Try to compute pharmacy income directly from PharmacyBill totals (preferred).
        // Some installations record pharmacy totals on `pharmacybills` table, which
        // represents the actual pharmacy amount (e.g., small retail sums). If those
        // values exist, return their sum. Otherwise fall back to the previous
        // approach (summing payments recorded against linked Billing records).
        $pharmacySum = $pharmacyRows->sum(function ($row) {
            return (float) ($row->net_amount ?? $row->total ?? $row->net ?? $row->paid_amount ?? 0);
        });

        if ($pharmacySum > 0) {
            return max(0, $pharmacySum - $returnDeduction);
        }

        // Next preference: compute pharmacy amount from BillItem rows with category 'Pharmacy'
        if (!empty($billingIds)) {
            // select only columns that exist in the current schema to avoid errors
            $cols = ['billing_id', 'id', 'category', 'item_id'];
            foreach (['total_amount', 'total', 'amount', 'net_amount', 'price', 'qty'] as $c) {
                if (Schema::hasColumn('bill_items', $c)) {
                    $cols[] = $c;
                }
            }

            // Treat both 'pharmacy' and 'medicine' categories as pharmacy items
                $pharmacyItems = BillItem::whereRaw('LOWER(category) IN (?,?)', ['pharmacy', 'medicine'])
                ->where('status', 'Active')
                ->whereIn('billing_id', $billingIds)
                ->get($cols);

            if ($pharmacyItems->isNotEmpty()) {
                // Sum per-billing pharmacy totals
                // Compute each item's effective total using any available field
                $pharmacyTotalsByBilling = $pharmacyItems->groupBy('billing_id')->map(function ($items) {
                        return $items->sum(function ($it) {
                            if ($it->net_amount !== null) {
                                return (float) $it->net_amount;
                            }
                        $val = 0;
                        if (!empty($it->total_amount)) {
                            $val = (float) $it->total_amount;
                        } elseif (!empty($it->total)) {
                            $val = (float) $it->total;
                        } elseif (!empty($it->amount)) {
                            $val = (float) $it->amount;
                        } elseif (!empty($it->net_amount)) {
                            $val = (float) $it->net_amount;
                        } elseif (isset($it->price) && isset($it->qty)) {
                            $val = (float) $it->price * (float) $it->qty;
                        } elseif (!empty($it->price)) {
                            $val = (float) $it->price;
                        }
                        return $val;
                    });
                });

                $netPharmacy = (float) $pharmacyTotalsByBilling->sum();

                if ($netPharmacy > 0) {
                    return max(0, $netPharmacy - $returnDeduction);
                }
            }
        }

        // Final fallback: sum payments + due collections against linked billings
        if (empty($billingIds)) {
            return 0;
        }

        $paymentsSum = (float) \App\Models\Payment::whereIn('billing_id', $billingIds)->sum('amount');
        $dueCollectionSum = (float) \App\Models\DueCollection::whereIn('billing_id', $billingIds)->sum('collected_amount');

        return max(0, ($paymentsSum + $dueCollectionSum) - $returnDeduction);
    }

    public function getDepartmentDashboardSummary(string $department, ?int $departmentId, array $dbRange, array $dateRange): array
    {
        $rows = collect();
        $expenseQuery = Expense::query()
            ->where('status', 'Active')
            ->whereBetween('date', [$dateRange[0]->toDateString(), $dateRange[1]->toDateString()]);

        if ($departmentId) {
            $expenseQuery->where('department_id', $departmentId);
        } else {
            $expenseQuery->whereRaw('1 = 0');
        }

        $expenses = (float) $expenseQuery->sum('amount');

        if ($department === 'pharmacy') {
            $bills = PharmacyBill::query()
                ->where('status', 'Active')
                ->whereBetween('date', [$dateRange[0]->toDateString(), $dateRange[1]->toDateString()])
                ->get();

            $billNumbers = $bills->pluck('bill_no')->filter()->unique()->values();
            $billingIdsByBillNumber = Billing::query()
                ->whereIn('bill_number', $billNumbers)
                ->pluck('id', 'bill_number');
            $returnsQuery = ProductReturn::query()
                ->where('return_type', 'customer')
                ->whereIn('status', ['approved', 'processed'])
                ->whereBetween('return_date', [$dateRange[0]->toDateString(), $dateRange[1]->toDateString()])
                ->where(function ($query) use ($billNumbers, $billingIdsByBillNumber) {
                    $query->whereIn('source_bill_no', $billNumbers);
                    if ($billingIdsByBillNumber->isNotEmpty()) {
                        $query->orWhereIn('billing_id', $billingIdsByBillNumber->values());
                    }
                })
                ->get(['source_bill_no', 'billing_id', 'total_amount']);
            $returnsByBill = $returnsQuery->groupBy(function ($return) {
                return $return->source_bill_no ?: 'billing:' . $return->billing_id;
            })->map(fn ($returns) => (float) $returns->sum('total_amount'));

            $rows = $bills->map(function ($bill) use ($returnsByBill, $billingIdsByBillNumber) {
                $net = (float) ($bill->net_amount ?? 0);
                $discount = (float) ($bill->discount_amount ?? 0) + (float) ($bill->extra_discount ?? 0);
                $billingId = $billingIdsByBillNumber[$bill->bill_no] ?? null;
                $refund = (float) ($returnsByBill[$bill->bill_no] ?? $returnsByBill['billing:' . $billingId] ?? 0);

                return [
                    'date' => $bill->date,
                    'bill_number' => $bill->bill_no,
                    'total' => (float) ($bill->subtotal ?? $net + $discount),
                    'discount' => $discount,
                    'refund' => $refund,
                    'income' => max(0, $net - $refund),
                    'pending' => max(0, $net - $refund - (float) ($bill->payment_amount ?? 0)),
                ];
            });

            $expenses += (float) \App\Models\SupplierPayment::query()
                ->whereBetween('payment_date', [$dateRange[0]->toDateString(), $dateRange[1]->toDateString()])
                ->sum('paid_amount');
        } else {
            $category = $department === 'radiology' ? 'Radiology' : 'Pathology';
            $billings = Billing::query()
                ->where('status', 'Active')
                ->whereBetween('created_at', $dbRange)
                ->whereHas('billItems', fn ($query) => $query
                    ->where('status', 'Active')
                    ->where(function ($itemQuery) use ($departmentId, $category) {
                        if ($departmentId) {
                            $itemQuery->where('department_id', $departmentId)
                                ->orWhere(function ($legacyQuery) use ($category) {
                                    $legacyQuery->whereNull('department_id')
                                        ->where('category', $category);
                                });
                        } else {
                            $itemQuery->where('category', $category);
                        }
                    }))
                ->with(['billItems' => fn ($query) => $query->where('status', 'Active')])
                ->get();

            $rows = $billings->map(function (Billing $billing) use ($category, $departmentId) {
                $allItems = $billing->billItems;
                $departmentItems = $allItems->filter(function ($item) use ($departmentId, $category) {
                    if ($departmentId && $item->department_id !== null) {
                        return (int) $item->department_id === $departmentId;
                    }

                    return strtolower((string) $item->category) === strtolower($category);
                });
                $departmentGross = (float) $departmentItems->sum('total_amount');
                $departmentItemNet = (float) $departmentItems->sum(fn ($item) => (
                    $item->net_amount !== null
                        ? (float) $item->net_amount
                        : (float) $item->total_amount - (float) ($item->discount ?? 0)
                ));
                $allGross = (float) $allItems->sum('total_amount');
                $share = $allGross > 0 ? $departmentGross / $allGross : 0;
                $extraDiscount = (float) ($billing->extra_flat_discount ?? 0) * $share;
                $income = max(0, $departmentItemNet - $extraDiscount);
                $discount = max(0, $departmentGross - $income);
                $refund = (float) ($billing->return_amt ?? 0) * $share;
                $payable = (float) ($billing->payable_amount ?? $billing->total ?? $allGross);
                $pending = $payable > 0
                    ? (float) ($billing->due_amount ?? 0) * min(1, $income / $payable)
                    : 0;

                return [
                    'date' => $billing->created_at,
                    'bill_number' => $billing->bill_number,
                    'total' => $departmentGross,
                    'discount' => $discount,
                    'refund' => $refund,
                    'income' => max(0, $income - $refund),
                    'pending' => max(0, $pending),
                ];
            });
        }

        $income = (float) $rows->sum('income');
        $refunds = (float) $rows->sum('refund');
        $discount = (float) $rows->sum('discount');
        $pending = (float) $rows->sum('pending');

        return [
            'income' => round($income, 2),
            'totalAmount' => round((float) $rows->sum('total'), 2),
            'netIncome' => round($department === 'pharmacy' ? $income - $expenses : $income, 2),
            'expenses' => round($expenses, 2),
            'pendingIncome' => round($pending, 2),
            'totalDiscountAmount' => round($discount, 2),
            'refunds' => round($refunds, 2),
            'transactions' => $rows->values()->all(),
        ];
    }

    public function countProductReturns(array $dateRange): float
    {
        return round((float) ProductReturn::query()
            ->whereBetween('return_date', [
                $dateRange[0]->toDateString(),
                $dateRange[1]->toDateString(),
            ])
            ->sum('total_amount'), 2);
    }

    // ✔ Blood Bank Income - Net Income After Discount (from Billing)
    public function countBloodBankIncome(array $dbRange, array $dateRange)
    {
        $billItems = BillItem::where('category', 'Blood Bank')
            ->where('status', 'Active')
            ->whereHas('billing', function ($q) use ($dbRange) {
                $q->whereBetween('created_at', $dbRange)
                    ->where('payment_status', '!=', 'Pending');
            })->get();

        if ($billItems->isEmpty()) {
            return 0;
        }

        $billingIds = $billItems->pluck('billing_id')->unique();
        $billings = Billing::whereIn('id', $billingIds)->get();

        return round((float) $billItems->sum('total_amount'), 2);
    }

    // ✔ Pathology Income - Net Income After Discount (from Billing)
    public function countPathologyIncome(array $dbRange, array $dateRange)
    {
        return (float) $this->getPathologyIncomeSummary($dbRange)['net'];
    }

    public function getPathologyIncomeSummary(array $dbRange): array
    {
        $billItems = BillItem::where('category', 'Pathology')
            ->where('status', 'Active')
            ->whereHas('billing', function ($q) use ($dbRange) {
                $q->whereBetween('created_at', $dbRange)
                  ->where('status', 'Active')
                  ->where(function ($paymentQuery) {
                      $paymentQuery->where('payment_status', '!=', 'Pending')
                          ->orWhere('due_amount', '<=', 0)
                          ->orWhere('paid_amt', '>', 0);
                  });
            })->get();

        if ($billItems->isEmpty()) {
            return ['total' => 0.0, 'discount' => 0.0, 'net' => 0.0];
        }

        $total = (float) $billItems->sum('total_amount');
        $itemNet = (float) $billItems->sum(function ($item) {
            return $item->net_amount !== null
                ? $item->net_amount
                : ((float) $item->total_amount - (float) ($item->discount ?? 0));
        });
        $billings = Billing::whereIn('id', $billItems->pluck('billing_id')->unique())
            ->get(['id', 'extra_flat_discount']);
        $extraDiscount = (float) $billings->sum('extra_flat_discount');
        $discount = max(0, $total - $itemNet + $extraDiscount);

        return [
            'total' => round($total, 2),
            'discount' => round($discount, 2),
            'net' => round(max(0, $itemNet - $extraDiscount), 2),
        ];
    }

    // ✔ Radiology Income - Net Income After Discount (from Billing)
    public function countRadiologyIncome(array $dbRange, array $dateRange)
    {
        $billItems = BillItem::where('category', 'Radiology')
            ->where('status', 'Active')
            ->whereHas('billing', function ($q) use ($dbRange) {
                $q->whereBetween('created_at', $dbRange)
                  ->where('payment_status', '!=', 'Pending');
            })->get();

        if ($billItems->isEmpty()) {
            return 0;
        }

        return round((float) $billItems->sum(function ($item) {
            return $item->net_amount !== null
                ? $item->net_amount
                : ((float) $item->total_amount - (float) ($item->discount ?? 0));
        }), 2);
    }

    public function countCategoryIncome(string $category, array $dbRange, array $separateCategories = []): float
    {
        $normalizedCategory = strtolower(trim($category));
        $separateCategories = array_map(static fn ($name) => strtolower(trim((string) $name)), $separateCategories);
        $hasSeparateUltrasound = (bool) array_intersect(
            $separateCategories,
            ['ultrasound', 'ultrasonogram', 'ultrasonography', 'usg']
        );
        $hasSeparateEcg = (bool) array_intersect($separateCategories, ['ecg', 'ekg']);
        $hasSeparateXray = (bool) array_intersect(
            $separateCategories,
            ['xray', 'x-ray', 'x ray', 'radiography']
        );
        $activeCategories = TestCategory::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->get(['id', 'name', 'parent_id']);
        $category = $activeCategories->first(fn ($item) => strtolower(trim($item->name)) === $normalizedCategory);
        $categoryIds = collect();
        if ($category) {
            $categoryIds = collect([$category->id]);
            $pendingIds = collect([$category->id]);
            while ($pendingIds->isNotEmpty()) {
                $childIds = $activeCategories->whereIn('parent_id', $pendingIds)->pluck('id');
                $pendingIds = $childIds->diff($categoryIds)->values();
                $categoryIds = $categoryIds->merge($pendingIds)->unique()->values();
            }
        }
        $categoryTestIds = $categoryIds->isNotEmpty()
            ? Test::query()->whereIn('test_category_id', $categoryIds)->pluck('id')
            : collect();
        $categoryTypeTestIds = Test::query()
            ->whereRaw('LOWER(category_type) = ?', [$normalizedCategory])
            ->pluck('id');
        $ecgTestIds = Test::query()
            ->whereRaw('LOWER(category_type) IN (?, ?)', ['ecg', 'ekg'])
            ->pluck('id');
        $ultrasoundTestIds = Test::query()
            ->whereRaw('LOWER(category_type) IN (?, ?, ?, ?)', ['ultrasound', 'ultrasonogram', 'ultrasonography', 'usg'])
            ->pluck('id');
        $categoryIncomeTestIds = $categoryTestIds->merge($categoryTypeTestIds)->unique()->values();
        $separatePathologyTestIds = collect();
        $separateRadiologyTestIds = collect();
        foreach ($separateCategories as $separateCategory) {
            if ($separateCategory === 'pathology' || in_array($separateCategory, [
                'radiology', 'ultrasound', 'ultrasonogram', 'ultrasonography', 'usg',
                'ecg', 'ekg', 'xray', 'x-ray', 'x ray', 'radiography',
            ], true)) {
                continue;
            }
            $separate = $activeCategories->first(fn ($item) => strtolower(trim($item->name)) === $separateCategory);
            if (!$separate) {
                continue;
            }
            $ancestorIds = collect([$separate->id]);
            $visitedAncestorIds = [(int) $separate->id => true];
            $ancestor = $separate;
            while ($ancestor?->parent_id) {
                $ancestor = $activeCategories->firstWhere('id', $ancestor->parent_id);
                if (!$ancestor) {
                    break;
                }
                $ancestorId = (int) $ancestor->id;
                if (isset($visitedAncestorIds[$ancestorId])) {
                    break;
                }
                $visitedAncestorIds[$ancestorId] = true;
                $ancestorIds->push($ancestor->id);
            }
            $isPathologyCategory = $ancestorIds->contains(fn ($id) => strtolower(trim((string) $activeCategories->firstWhere('id', $id)?->name)) === 'pathology')
                || Test::query()->where('test_category_id', $separate->id)->whereRaw('LOWER(category_type) = ?', ['pathology'])->exists();
            $isRadiologyCategory = $ancestorIds->contains(fn ($id) => strtolower(trim((string) $activeCategories->firstWhere('id', $id)?->name)) === 'radiology')
                || Test::query()->where('test_category_id', $separate->id)->whereRaw('LOWER(category_type) IN (?, ?, ?, ?)', ['radiology', 'ultrasound', 'ecg', 'xray'])->exists();
            if ($isPathologyCategory) {
                $separatePathologyTestIds = $separatePathologyTestIds->merge(
                    Test::query()->where('test_category_id', $separate->id)->whereRaw('LOWER(category_type) = ?', ['pathology'])->pluck('id')
                );
            }
            if ($isRadiologyCategory) {
                $separateRadiologyTestIds = $separateRadiologyTestIds->merge(
                    Test::query()->where('test_category_id', $separate->id)->pluck('id')
                );
            }
        }
        $separatePathologyTestIds = $separatePathologyTestIds->unique()->values();
        $separateRadiologyTestIds = $separateRadiologyTestIds->unique()->values();
        $query = BillItem::query()
            ->where('status', 'Active')
            ->where(function ($categoryQuery) use ($normalizedCategory, $hasSeparateUltrasound, $hasSeparateEcg, $hasSeparateXray, $separatePathologyTestIds, $separateRadiologyTestIds, $categoryTestIds, $categoryIncomeTestIds, $ecgTestIds, $ultrasoundTestIds) {
                if (in_array($normalizedCategory, ['ultrasound', 'ultrasonogram', 'ultrasonography', 'usg'], true)) {
                    $categoryQuery
                        ->where(function ($ultrasoundItems) use ($ultrasoundTestIds) {
                            $ultrasoundItems
                                ->whereRaw('LOWER(category) IN (?,?,?,?)', ['ultrasound', 'ultrasonogram', 'ultrasonography', 'usg'])
                                ->orWhere(function ($linkedItems) use ($ultrasoundTestIds) {
                                    $linkedItems->where(function ($categoryMatch) {
                                        $categoryMatch->whereRaw('LOWER(category) LIKE ?', ['radiology%'])
                                            ->orWhereRaw('LOWER(category) = ?', ['ultrasound']);
                                    })
                                        ->whereIn('item_id', $ultrasoundTestIds->all());
                                });
                        })
                        ->orWhere(function ($radiologyQuery) {
                            $radiologyQuery->whereRaw('LOWER(category) = ?', ['radiology'])
                                ->where(function ($nameQuery) {
                                    $nameQuery->whereRaw('LOWER(item_name) LIKE ?', ['%ultrasound%'])
                                        ->orWhereRaw('LOWER(item_name) LIKE ?', ['%ultrasonogram%'])
                                        ->orWhereRaw('LOWER(item_name) LIKE ?', ['%ultrasonography%'])
                                        ->orWhereRaw('LOWER(item_name) LIKE ?', ['%ultrason%'])
                                        ->orWhereRaw('LOWER(item_name) LIKE ?', ['%usg%']);
                                });
                        });
                } elseif (in_array($normalizedCategory, ['ecg', 'ekg'], true)) {
                    $categoryQuery->where(function ($ecgItems) use ($ecgTestIds) {
                        $ecgItems->whereRaw('LOWER(category) IN (?, ?)', ['ecg', 'ekg'])
                            ->orWhere(function ($linkedItems) use ($ecgTestIds) {
                                $linkedItems->where(function ($categoryMatch) {
                                    $categoryMatch->whereRaw('LOWER(category) LIKE ?', ['radiology%'])
                                        ->orWhereRaw('LOWER(category) = ?', ['ecg']);
                                })
                                    ->whereIn('item_id', $ecgTestIds->all());
                            });
                    });
                } elseif (str_contains($normalizedCategory, 'radiology')) {
                    $excludedRadiologyTestIds = $separateRadiologyTestIds
                        ->merge($hasSeparateUltrasound ? $ultrasoundTestIds : collect())
                        ->merge($hasSeparateEcg ? $ecgTestIds : collect())
                        ->unique()
                        ->values();

                    $categoryQuery->where(function ($radiologyGroup) use ($excludedRadiologyTestIds, $hasSeparateUltrasound, $hasSeparateEcg) {
                        $radiologyGroup->where(function ($radiologyItems) use ($excludedRadiologyTestIds, $hasSeparateUltrasound) {
                            $radiologyItems->whereRaw('LOWER(category) LIKE ?', ['radiology%']);

                            if ($excludedRadiologyTestIds->isNotEmpty()) {
                                $radiologyItems->where(function ($linkedItems) use ($excludedRadiologyTestIds) {
                                    $linkedItems->whereNull('item_id')
                                        ->orWhereNotIn('item_id', $excludedRadiologyTestIds->all());
                                });
                            }

                            if ($hasSeparateUltrasound) {
                                $radiologyItems->where(function ($names) {
                                    $names->whereNull('item_name')
                                        ->orWhere(function ($notUltrasound) {
                                            $notUltrasound->whereRaw('LOWER(item_name) NOT LIKE ?', ['%ultrasound%'])
                                                ->whereRaw('LOWER(item_name) NOT LIKE ?', ['%ultrasonogram%'])
                                                ->whereRaw('LOWER(item_name) NOT LIKE ?', ['%ultrasonography%'])
                                                ->whereRaw('LOWER(item_name) NOT LIKE ?', ['%ultrason%'])
                                                ->whereRaw('LOWER(item_name) NOT LIKE ?', ['%usg%']);
                                        });
                                });
                            }
                        });

                        if (!$hasSeparateEcg) {
                            $radiologyGroup->orWhereRaw('LOWER(category) IN (?,?)', ['ecg', 'ekg']);
                        }
                        if (!$hasSeparateUltrasound) {
                            $radiologyGroup->orWhereRaw('LOWER(category) IN (?,?,?,?)', ['ultrasound', 'ultrasonogram', 'ultrasonography', 'usg']);
                        }
                    });
                } elseif ($normalizedCategory === 'pathology') {
                    $categoryQuery->whereRaw('LOWER(category) = ?', ['pathology']);
                    if ($separatePathologyTestIds->isNotEmpty()) {
                        $categoryQuery->where(function ($pathologyItems) use ($separatePathologyTestIds) {
                            $pathologyItems->whereNull('item_id')
                                ->orWhereNotIn('item_id', $separatePathologyTestIds->all());
                        });
                    }
                } elseif ($categoryIncomeTestIds->isNotEmpty()) {
                    $categoryQuery->where(function ($categoryItems) use ($normalizedCategory, $categoryIncomeTestIds) {
                        $categoryItems->whereRaw('LOWER(category) = ?', [$normalizedCategory])
                            ->orWhere(function ($linkedItems) use ($categoryIncomeTestIds) {
                                $linkedItems->whereRaw('LOWER(category) IN (?, ?, ?)', ['pathology', 'radiology', 'ecg'])
                                    ->whereIn('item_id', $categoryIncomeTestIds->all());
                            });
                    });
                } else {
                    $categoryQuery->whereRaw('LOWER(category) = ?', [$normalizedCategory]);
                }
            })
            ->whereHas('billing', function ($query) use ($dbRange) {
                $query->whereBetween('created_at', $dbRange)
                    ->where('status', 'Active')
                    ->where(function ($paymentQuery) {
                        $paymentQuery->where('payment_status', '!=', 'Pending')
                            ->orWhere('due_amount', '<=', 0)
                            ->orWhere('paid_amt', '>', 0);
                    });
            })
            ->get();

        if ($query->isEmpty()) {
            return 0.0;
        }

        $categoryNetByBilling = $query->groupBy('billing_id')->map(function ($items) use ($normalizedCategory, $hasSeparateUltrasound, $hasSeparateEcg, $hasSeparateXray) {
            $totals = ['eligible' => 0.0, 'disposable' => 0.0];

            foreach ($items as $item) {
                $itemCategory = strtolower(trim((string) $item->category));
                $itemName = strtolower(trim((string) $item->item_name));

                if (str_contains($normalizedCategory, 'radiology')) {
                    $isUltrasound = str_contains($itemName, 'ultrasound')
                        || str_contains($itemName, 'ultrasonogram')
                        || str_contains($itemName, 'ultrasonography')
                        || str_contains($itemName, 'ultrason')
                        || str_contains($itemName, 'usg');
                    $isXray = str_contains($itemName, 'x-ray')
                        || str_contains($itemName, 'xray')
                        || str_contains($itemName, 'radiograph');
                    if (($isUltrasound && $hasSeparateUltrasound)
                        || ($isXray && $hasSeparateXray)
                        || ($itemCategory === 'ecg' && $hasSeparateEcg)) {
                        continue;
                    }
                }

                if ($this->isDisposableIncomeItem($item)) {
                    $totals['disposable'] += (float) $item->total_amount;
                    continue;
                }

                $totals['eligible'] += $item->net_amount !== null
                    ? (float) $item->net_amount
                    : max(0, (float) $item->total_amount - (float) ($item->discount ?? 0));

            }

            return $totals;
        });

        $billingIds = $categoryNetByBilling->keys()->filter()->values();
        $allNetByBilling = BillItem::query()
            ->where('status', 'Active')
            ->whereIn('billing_id', $billingIds->all())
            ->get(['billing_id', 'category', 'item_name', 'total_amount', 'discount', 'net_amount'])
            ->groupBy('billing_id')
            ->map(fn ($items) => (float) $items
                ->reject(fn ($item) => $this->isDisposableIncomeItem($item))
                ->sum(fn ($item) => $item->net_amount !== null
                    ? (float) $item->net_amount
                    : max(0, (float) $item->total_amount - (float) ($item->discount ?? 0))));

        $extraDiscountByBilling = Billing::query()
            ->whereIn('id', $billingIds->all())
            ->pluck('extra_flat_discount', 'id');

        $categoryIncomeTotal = $categoryNetByBilling->reduce(function ($total, $categoryAmounts, $billingId) use ($allNetByBilling, $extraDiscountByBilling) {
            $allNet = (float) ($allNetByBilling->get($billingId) ?? 0);
            $extraDiscount = max(0, (float) ($extraDiscountByBilling->get($billingId) ?? 0));
            $categoryNet = (float) ($categoryAmounts['eligible'] ?? 0);
            $disposableAmount = (float) ($categoryAmounts['disposable'] ?? 0);
            if ($allNet <= 0 || $extraDiscount <= 0) {
                return $total + $categoryNet + $disposableAmount;
            }

            $allocatedExtraDiscount = min($categoryNet, $extraDiscount * ($categoryNet / $allNet));
            return $total + $disposableAmount + max(0, $categoryNet - $allocatedExtraDiscount);
        }, 0);

        return round((float) $categoryIncomeTotal, 2);
    }

    public function countMainCategoryIncome(int $mainCategoryId, array $dbRange, ?string $categoryType = null): float
    {
        $mainCategory = MainCategory::query()
            ->whereKey($mainCategoryId)
            ->first(['id', 'name']);
        $isEcgMainCategory = in_array(
            strtolower(trim((string) $mainCategory?->name)),
            ['ecg', 'ekg'],
            true
        );

        $testIds = Test::query()
            ->where('main_category_id', $mainCategoryId)
            ->where('status', 'Active')
            ->when($categoryType !== null, fn ($query) => $query->whereRaw('LOWER(category_type) = ?', [strtolower($categoryType)]))
            ->when($isEcgMainCategory, function ($query) use ($categoryType) {
                $query->orWhere(function ($unassignedEcgTests) use ($categoryType) {
                    $unassignedEcgTests
                        ->whereNull('main_category_id')
                        ->where('status', 'Active')
                        ->whereRaw('LOWER(category_type) IN (?, ?)', ['ecg', 'ekg'])
                        ->when(
                            $categoryType !== null,
                            fn ($query) => $query->whereRaw('LOWER(category_type) = ?', [strtolower($categoryType)])
                        );
                });
            })
            ->pluck('id');

        return $this->countLinkedTestIncome($testIds, $dbRange, $categoryType);
    }

    public function countMainCategoryTypeIncome(string $categoryType, array $dbRange, ?array $mainCategoryIds = null): float
    {
        $normalizedCategoryType = strtolower(trim($categoryType));
        $testsQuery = Test::query()
            ->where('status', 'Active')
            ->where(function ($query) use ($normalizedCategoryType, $mainCategoryIds) {
                $query->where(function ($radiologyTests) use ($normalizedCategoryType, $mainCategoryIds) {
                    $radiologyTests
                        ->whereNotNull('main_category_id')
                        ->whereRaw('LOWER(category_type) = ?', [$normalizedCategoryType])
                        ->when($mainCategoryIds !== null, fn ($query) => $query->whereIn('main_category_id', $mainCategoryIds));
                });

                if (
                    $normalizedCategoryType === 'radiology'
                    && $mainCategoryIds === null
                    && !MainCategory::query()
                        ->where('status', 'Active')
                        ->whereRaw('LOWER(name) IN (?, ?)', ['ecg', 'ekg'])
                        ->exists()
                ) {
                    $query->orWhereRaw('LOWER(category_type) IN (?, ?)', ['ecg', 'ekg']);
                }
            });
        $testIds = $testsQuery->pluck('id');

        return $this->countLinkedTestIncome($testIds, $dbRange, $normalizedCategoryType);
    }

    public function countDiagnosticIncome(string $categoryType, array $testIds, array $dbRange): float
    {
        return $this->countLinkedTestIncome(
            collect($testIds)->unique()->values(),
            $dbRange,
            strtolower(trim($categoryType))
        );
    }

    private function countLinkedTestIncome($testIds, array $dbRange, ?string $categoryType): float
    {
        if ($testIds->isEmpty()) {
            return 0.0;
        }

        $billItems = BillItem::query()
            ->where('status', 'Active')
            ->whereIn('category', $categoryType === null
                ? ['Pathology', 'Radiology']
                : [ucfirst(strtolower($categoryType))])
            ->whereIn('item_id', $testIds)
            ->whereHas('billing', function ($query) use ($dbRange) {
                $query->whereBetween('created_at', $dbRange)
                    ->where('status', 'Active')
                    ->where(function ($paymentQuery) {
                        $paymentQuery->where('payment_status', '!=', 'Pending')
                            ->orWhere('due_amount', '<=', 0)
                            ->orWhere('paid_amt', '>', 0);
                    });
            })
            ->get(['billing_id', 'category', 'item_name', 'total_amount', 'discount', 'net_amount']);

        if ($billItems->isEmpty()) {
            return 0.0;
        }

        $categoryNetByBilling = $billItems
            ->groupBy('billing_id')
            ->map(function ($items) {
                $totals = ['eligible' => 0.0, 'disposable' => 0.0];
                foreach ($items as $item) {
                    if ($this->isDisposableIncomeItem($item)) {
                        $totals['disposable'] += (float) $item->total_amount;
                        continue;
                    }

                    $totals['eligible'] += $item->net_amount !== null
                        ? (float) $item->net_amount
                        : max(0, (float) $item->total_amount - (float) ($item->discount ?? 0));
                }

                return $totals;
            });

        $billingIds = $categoryNetByBilling->keys()->filter()->values();
        $allNetByBilling = BillItem::query()
            ->where('status', 'Active')
            ->whereIn('billing_id', $billingIds->all())
            ->get(['billing_id', 'category', 'item_name', 'total_amount', 'discount', 'net_amount'])
            ->groupBy('billing_id')
            ->map(fn ($items) => (float) $items
                ->reject(fn ($item) => $this->isDisposableIncomeItem($item))
                ->sum(fn ($item) => $item->net_amount !== null
                    ? (float) $item->net_amount
                    : max(0, (float) $item->total_amount - (float) ($item->discount ?? 0))));

        $extraDiscountByBilling = Billing::query()
            ->whereIn('id', $billingIds->all())
            ->pluck('extra_flat_discount', 'id');

        $income = $categoryNetByBilling->reduce(function ($total, $categoryAmounts, $billingId) use ($allNetByBilling, $extraDiscountByBilling) {
            $allNet = (float) ($allNetByBilling->get($billingId) ?? 0);
            $extraDiscount = max(0, (float) ($extraDiscountByBilling->get($billingId) ?? 0));
            $categoryNet = (float) ($categoryAmounts['eligible'] ?? 0);
            $disposableAmount = (float) ($categoryAmounts['disposable'] ?? 0);
            if ($allNet <= 0 || $extraDiscount <= 0) {
                return $total + $categoryNet + $disposableAmount;
            }

            $allocatedExtraDiscount = min($categoryNet, $extraDiscount * ($categoryNet / $allNet));
            return $total + $disposableAmount + max(0, $categoryNet - $allocatedExtraDiscount);
        }, 0);

        return round((float) $income, 2);
    }

    // ✔ OPD Income (Today paid) - Actual paid amount
    public function countOpdIncome(array $dateRange)
    {
        // Primary source: OPD appointments (OpdPatient paid_amount)
        $opdPaid = OpdPatient::where('status', 'Active')
            ->whereNull('deleted_at')
            ->where('payment_status', '!=', 'Pending')
            ->whereBetween('appointment_date', [
                $dateRange[0]->toDateString(),
                $dateRange[1]->toDateString(),
            ])
            ->sum('paid_amount');

        // Additional source: Bill items categorized as 'OPD' or 'Appointment'
        $billItemQuery = BillItem::whereRaw('LOWER(category) IN (?,?)', ['opd', 'appointment'])
            ->where('status', 'Active')
            ->whereHas('billing', function ($q) use ($dateRange) {
                $q->whereBetween('created_at', [$dateRange[0], $dateRange[1]])
                    ->where('status', 'Active')
                    ->where('payment_status', '!=', 'Pending');
            });

        if ($billItemQuery->exists()) {
            $items = $billItemQuery->get();
            $totalsByBilling = $items->groupBy('billing_id')->map(function ($items) {
                return (float) $items->sum('total_amount');
            });

            $net = 0.0;
            $net = (float) $items->sum(function ($item) {
                return $item->net_amount !== null
                    ? $item->net_amount
                    : ((float) $item->total_amount - (float) ($item->discount ?? 0));
            });

            $opdPaid += round($net, 2);
        }

        return (float) $opdPaid;
    }

    public function getDoctorVisitAppointmentSummary(array $dateRange): array
    {
        $appointments = Appoinment::query()
            ->with(['admin:id,first_name,last_name', 'opdPatient:id,reference,paid_amount,balance_amount'])
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->where('appoinment_status', '!=', 'Cancelled')
            ->whereBetween('appoinment_date', [
                $dateRange[0]->copy()->startOfDay(),
                $dateRange[1]->copy()->endOfDay(),
            ])
            ->get(['doctor_id', 'doctor_fee', 'discount_percentage', 'transaction_id']);

        $netAmount = static function ($appointment): float {
            $fee = (float) ($appointment->doctor_fee ?? 0);
            $discount = (float) ($appointment->discount_percentage ?? 0);

            return max(0, $fee - ($fee * $discount / 100));
        };

        $byDoctor = $appointments
            ->groupBy('doctor_id')
            ->map(function ($doctorAppointments) use ($netAmount) {
                $doctor = $doctorAppointments->first()->admin;

                return [
                    'doctor_id' => $doctorAppointments->first()->doctor_id,
                    'doctor_name' => $doctor?->name ?? 'Unknown Doctor',
                    'appointment_count' => $doctorAppointments->count(),
                    'fee' => round((float) $doctorAppointments->sum($netAmount), 2),
                    'amount' => round((float) $doctorAppointments->sum(fn ($appointment) => (float) ($appointment->opdPatient?->paid_amount ?? 0)), 2),
                    'due_amount' => round((float) $doctorAppointments->sum(fn ($appointment) => max(0, $netAmount($appointment) - (float) ($appointment->opdPatient?->paid_amount ?? 0))), 2),
                ];
            })
            ->sortBy('doctor_name')
            ->values();

        return [
            'total' => round((float) $byDoctor->sum('fee'), 2),
            'byDoctor' => $byDoctor->all(),
        ];
    }

    // Disposable items are excluded from discount allocation, so report their gross amount.
    public function countDisposableIncome(array $dbRange, array $dateRange)
    {
        $billItems = BillItem::whereRaw('LOWER(category) = ?', ['disposable'])
            ->where('status', 'Active')
            ->whereHas('billing', function ($q) use ($dbRange) {
                $q->whereBetween('created_at', $dbRange)
                    ->where('status', 'Active')
                    ->where('payment_status', '!=', 'Pending');
            })->get();

        if ($billItems->isEmpty()) {
            return 0;
        }

        return round((float) $billItems->sum('total_amount'), 2);
    }

    // ✔ IPD Income (from IPD billing) - Collected amount
    public function countIpdIncome(array $dbRange)
    {
        // Collect billing IDs linked to IPD patients within the requested DB range.
        $billingIdsFromIpd = IpdPatient::query()
            ->whereIn('status', ['Active', 'Inactive'])
            ->whereNotNull('billing_id')
            ->whereHas('billing', function ($q) use ($dbRange) {
                $q->whereBetween('created_at', $dbRange)
                    ->where('status', 'Active');
            })->pluck('billing_id')->unique()->values()->toArray();

        // Also include billing IDs which contain bill_items categorized as 'IPD'
        $itemBillingIds = BillItem::query()
            ->whereRaw('LOWER(category) = ?', ['ipd'])
            ->where('status', 'Active')
            ->whereHas('billing', function ($q) use ($dbRange) {
                $q->whereBetween('created_at', $dbRange)
                    ->where('status', 'Active');
            })->pluck('billing_id')->unique()->values()->toArray();

        // Merge both sources of billing ids (from ipd_patient links and from IPD bill items)
        $billingIds = array_values(array_unique(array_merge($billingIdsFromIpd, $itemBillingIds)));

        if (!empty($itemBillingIds)) {
            $ipdItems = BillItem::whereRaw('LOWER(category) = ?', ['ipd'])
                ->where('status', 'Active')
                ->whereIn('billing_id', $itemBillingIds)
                ->get(['total_amount', 'discount', 'net_amount']);

            return round((float) $ipdItems->sum(function ($item) {
                return $item->net_amount !== null
                    ? $item->net_amount
                    : ((float) $item->total_amount - (float) ($item->discount ?? 0));
            }), 2);
        }

        // Sum payments that are either explicitly linked to an IPD patient OR
        // are linked to a billing that belongs to an IPD discharge billing.
        $paymentQuery = \App\Models\Payment::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->whereBetween('created_at', $dbRange)
            ->where(function ($q) use ($billingIds) {
                $q->whereNotNull('ipd_patient_id');
                if (!empty($billingIds)) {
                    $q->orWhereIn('billing_id', $billingIds);
                }
            });

        $paymentsSum = (float) $paymentQuery->sum('amount');

        // Removed verbose debug logging to reduce log volume

        if ($paymentsSum > 0) {
            return $paymentsSum;
        }

        // Final fallback: sum paid_amt on billings linked to ipdpatients (legacy)
        if (empty($billingIds)) {
            return 0.0;
        }

        $billingPaidSum = (float) \App\Models\Billing::whereIn('id', $billingIds)
            ->where('status', 'Active')
            ->where('payment_status', '!=', 'Pending')
            ->sum('paid_amt');

        // Removed fallback logging to keep logs concise

        return $billingPaidSum;
    }

    // ✔ Pending Income (outstanding dues for the current dashboard filter range)
    // Billing due amounts use created_at, OPD balances use appointment_date or created_at.
    public function countPendingIncome(array $dbRange = null, array $dateRange = null)
    {
        $billingQuery = Billing::query()
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->where('due_amount', '>', 0);

        if (!empty($dbRange)) {
            $billingQuery->whereBetween('created_at', $dbRange);
        }

        $billingDue = (float) $billingQuery->sum('due_amount');

        $opdQuery = OpdPatient::query()
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->where('balance_amount', '>', 0);

        if (!empty($dateRange)) {
            $opdQuery->where(function ($query) use ($dateRange) {
                $query->whereBetween('appointment_date', [
                    $dateRange[0]->toDateString(),
                    $dateRange[1]->toDateString(),
                ])
                ->orWhereBetween('created_at', [
                    $dateRange[0]->toDateTimeString(),
                    $dateRange[1]->toDateTimeString(),
                ]);
            });
        }

        $opdDue = (float) $opdQuery->sum('balance_amount');

        return max(0, $billingDue + $opdDue);
    }

    // ✔ Total Income (module incomes + pending dues)
    public function countTotalIncome(array $dbRange, array $dateRange)
    {
        $dateConditions = [
            'single_date_range' => $dbRange,
        ];

        $billRows = $this->accountingService->getBillRowsByDate($dateConditions);
        $incomeTotals = $this->accountingService->calculateFinalIncomeTotals($billRows, $dateConditions);

        return (float) ($incomeTotals['income_before_expenses'] ?? 0);
    }

    // ✔ Total Discount (Today)
    public function countTotalDiscount(array $dbRange)
    {
        $billTotals = $this->getBillAccountingTotals($dbRange);

        return (float) ($billTotals['total_discount'] ?? 0);
    }

    public function addDiscountRoundingToDiagnosticIncomes(
        float $pathologyIncome,
        float $radiologyIncome,
        array $dbRange
    ): array {
        $adjustmentCents = (int) round(
            (float) ($this->getBillAccountingTotals($dbRange)['discount_rounding_adjustment'] ?? 0) * 100,
            0,
            PHP_ROUND_HALF_UP
        );
        if ($adjustmentCents === 0) {
            return [$pathologyIncome, $radiologyIncome];
        }

        $pathologyCents = $this->toCents($pathologyIncome);
        $radiologyCents = $this->toCents($radiologyIncome);
        $combinedIncomeCents = max(0, $pathologyCents) + max(0, $radiologyCents);
        if ($combinedIncomeCents === 0) {
            return [$pathologyIncome, $radiologyIncome];
        }

        $pathologyAdjustmentCents = (int) round(
            $adjustmentCents * (max(0, $pathologyCents) / $combinedIncomeCents),
            0,
            PHP_ROUND_HALF_UP
        );
        $radiologyAdjustmentCents = $adjustmentCents - $pathologyAdjustmentCents;

        return [
            (float) ($pathologyCents + $pathologyAdjustmentCents) / 100,
            (float) ($radiologyCents + $radiologyAdjustmentCents) / 100,
        ];
    }

    private function getBillAccountingTotals(array $dbRange): array
    {
        $rangeKey = implode('|', array_map(
            static fn ($date) => $date instanceof \DateTimeInterface
                ? $date->format('Y-m-d H:i:s')
                : (string) $date,
            $dbRange
        ));

        if (!array_key_exists($rangeKey, $this->billAccountingTotalsByRange)) {
            $dateConditions = ['single_date_range' => $dbRange];
            $billRows = $this->accountingService->getBillRowsByDate($dateConditions);
            $this->billAccountingTotalsByRange[$rangeKey] = $this->accountingService->calculateBillTotals($billRows);
        }

        return $this->billAccountingTotalsByRange[$rangeKey];
    }

    private function toCents(float $amount): int
    {
        return (int) round(round($amount, 2) * 100, 0, PHP_ROUND_HALF_UP);
    }

    // ✔ Expense (Daily)
    public function countExpense(array $dateRange)
    {
        return $this->accountingService->getExpenseTotal([
            'single_date_range' => $dateRange,
        ]);
    }

    private function countInitialAppointmentReceipts(array $dbRange): float
    {
        return $this->accountingService->getInitialAppointmentReceipts([
            'single_date_range' => $dbRange,
        ]);
    }

    // ✔ Net Income uses the same paid/due/expense/refund basis as reports.
    public function countNetIncome(array $dbRange, array $dateRange)
    {
        $dateConditions = [
            'single_date_range' => $dbRange,
        ];

        $billRows = $this->accountingService->getBillRowsByDate($dateConditions);
        $incomeTotals = $this->accountingService->calculateFinalIncomeTotals($billRows, $dateConditions);

        return round((float) ($incomeTotals['final_income'] ?? 0), 2);
    }

    // ✔ Total Refunds (Daily) = Sum of processed refunds from refund transactions
    public function countRefunds(array $dbRange, array $dateRange)
    {
        $dateConditions = [
            'single_date_range' => $dbRange,
        ];

        return $this->accountingService->getRefundTotal($dateConditions);
    }

    // Referral Commission (dashboard card): pending commission amount
    // Sum of (total_commission_amount - paid_amount) for active referrals.
    public function countReferralCommissionPending(array $dbRange = null): float
    {
        $query = Referral::query()
            ->where('status', 'Active')
            ->whereNull('deleted_at');

        if (!empty($dbRange)) {
            $query->whereBetween('created_at', $dbRange);
        }

        $rows = $query->get(['total_commission_amount', 'paid_amount']);

        $pending = $rows->sum(function ($row) {
            $total = (float) ($row->total_commission_amount ?? 0);
            $paid = (float) ($row->paid_amount ?? 0);
            return max(0, $total - $paid);
        });

        return (float) round($pending, 2);
    }

    // Distinct referral payee IDs that still have pending commission.
    public function countReferralCommissionPendingPayees(array $dbRange = null): int
    {
        $query = Referral::query()
            ->where('status', 'Active')
            ->whereNull('deleted_at');

        if (!empty($dbRange)) {
            $query->whereBetween('created_at', $dbRange);
        }

        $rows = $query->get(['payee_id', 'total_commission_amount', 'paid_amount']);

        return (int) $rows
            ->filter(function ($row) {
                $total = (float) ($row->total_commission_amount ?? 0);
                $paid = (float) ($row->paid_amount ?? 0);
                return max(0, $total - $paid) > 0;
            })
            ->pluck('payee_id')
            ->filter()
            ->unique()
            ->count();
    }

    private function isDisposableIncomeItem(BillItem $item): bool
    {
        $category = strtolower(trim((string) $item->category));
        if ($category === 'disposable') {
            return true;
        }

        if ($category !== 'pathology') {
            return false;
        }

        $name = strtolower((string) $item->item_name);
        foreach (['disposable', 'tube', 'butterfly', 'needle'] as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
