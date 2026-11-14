<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DoctorSummaryController extends Controller
{
    public function index(Request $request)
    {
        $debugLog = storage_path('logs/doctor-summary-debug.log');
        try {
            $mode = $request->query('mode'); // test|referrer|technologist|pathologist|collector or null
        $term = $request->query('q') ?? $request->query('term') ?? null;
        $subcat = $request->query('subcat') ?? null;
            if (
                (!is_string($subcat) || trim($subcat) === '')
                && is_string($term)
                && trim($term) !== ''
            ) {
                $normalizedSearchCategory = Str::lower((string) preg_replace('/[\s\-_.\/]+/u', '', trim($term)));
                $categoryTables = ['testcategories', 'main_categories'];

                foreach ($categoryTables as $categoryTable) {
                    if (!Schema::hasTable($categoryTable)) {
                        continue;
                    }

                    $categoryQuery = DB::table($categoryTable)
                        ->whereRaw(
                            "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(name), ' ', ''), '-', ''), '_', ''), '/', ''), '.', '')) = ?",
                            [$normalizedSearchCategory]
                        );

                    if (Schema::hasColumn($categoryTable, 'deleted_at')) {
                        $categoryQuery->whereNull('deleted_at');
                    }
                    if (Schema::hasColumn($categoryTable, 'status')) {
                        $categoryQuery->where('status', 'Active');
                    }

                    $matchedCategoryName = $categoryQuery->value('name');
                    if (is_string($matchedCategoryName) && trim($matchedCategoryName) !== '') {
                        $subcat = trim($matchedCategoryName);
                        $term = null;
                        break;
                    }
                }
            }
            $from = $request->query('from');
        $to = $request->query('to');
        $perPageInput = $request->query('numOfData') ?? $request->query('per_page') ?? 10;
        $allPerPage = is_string($perPageInput) && strtolower(trim($perPageInput)) === 'all';
        $perPage = $allPerPage ? 10 : (int) $perPageInput;
        if ($perPage < 1) {
            $perPage = 10;
        }
        $page = (int) ($request->query('page') ?? 1);
        if ($allPerPage) {
            $page = 1;
        }

            @file_put_contents($debugLog, date('Y-m-d H:i:s') . " - ENTER mode={$mode} term={$term} from={$from} to={$to} perPage={$perPage} page={$page}" . PHP_EOL, FILE_APPEND);

        // Helper to apply date range on a query using billing.created_at or item timestamps
        $applyDateRange = function ($query, $field = 'billings.created_at') use ($from, $to) {
            if ($from && $to) {
                // Use date-only comparisons so providing YYYY-MM-DD includes the whole day
                $query->whereDate($field, '>=', $from)
                      ->whereDate($field, '<=', $to);
            } elseif ($from) {
                $query->whereDate($field, '>=', $from);
            } elseif ($to) {
                $query->whereDate($field, '<=', $to);
            }
        };

        $hasBillNumberColumn = Schema::hasColumn('billings', 'bill_number');
        $resolveCompanyName = function (): string {
            if (
                !Schema::hasTable('web_settings')
                || !Schema::hasColumn('web_settings', 'company_name')
            ) {
                return '';
            }

            $settingsQuery = DB::table('web_settings');
            if (Schema::hasColumn('web_settings', 'deleted_at')) {
                $settingsQuery->whereNull('deleted_at');
            }
            if (Schema::hasColumn('web_settings', 'status')) {
                $settingsQuery->orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END");
            }

            return trim((string) $settingsQuery->orderByDesc('id')->value('company_name'));
        };
        $applyActiveBillItems = function ($query) {
            if (Schema::hasColumn('bill_items', 'deleted_at')) {
                $query->whereNull('bill_items.deleted_at');
            }

            if (Schema::hasColumn('bill_items', 'status')) {
                $query->where('bill_items.status', 'Active');
            }
        };
        $normalizeCategorySql = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(bill_items.category), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))";
        $normalizeItemNameSql = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(bill_items.item_name, '')), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))";
        $applyCategoryFilter = function ($query) use ($subcat, $normalizeCategorySql, $normalizeItemNameSql) {
        if (!is_string($subcat) || trim($subcat) === '') {
            return;
        }

        $categoryParts = preg_split('/\s*>\s*/u', trim($subcat));
        $selectedCategory = trim((string) end($categoryParts));
        $normalizedUnicodeCategory = Str::lower((string) preg_replace('/[\s\-_.\/]+/u', '', $selectedCategory));
        $normalizedCategory = Str::lower((string) preg_replace('/[^a-z0-9]/i', '', $selectedCategory));
        $ultrasoundAliases = ['ultrasound', 'ultrasonogram', 'ultrasonography', 'sonography', 'usg', 'sono'];
        $isUltrasoundCategory = in_array($normalizedCategory, $ultrasoundAliases, true)
            || in_array($normalizedUnicodeCategory, ['আল্ট্রাসনোগ্রাম', 'আল্ট্রাসাউন্ড', 'ইউএসজি'], true);
        $baseMatchers = [$normalizedCategory !== '' ? $normalizedCategory : $normalizedUnicodeCategory];
        $categoryMatchers = $isUltrasoundCategory
            ? array_values(array_unique(array_merge($ultrasoundAliases, ['আল্ট্রাসনোগ্রাম', 'আল্ট্রাসাউন্ড', 'ইউএসজি'])))
            : array_values(array_unique(array_filter($baseMatchers, static fn ($value) => $value !== '')));

        if ($categoryMatchers === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $categoryMasterMatchFound = false;
        $selectedIsRadiologyCategory = $isUltrasoundCategory === false && (
            $normalizedCategory === 'radiology' || $normalizedCategory === 'xray' || $normalizedCategory === 'ct' || $normalizedCategory === 'mri'
            || in_array($normalizedUnicodeCategory, ['রেডিওলজি', 'এক্সরে', 'সিটি', 'এমআরআই'], true)
        );
        $radiologyConflictMatchers = [
            'ultrasound', 'ultrasonogram', 'ultrasonography', 'sonography', 'usg', 'sono', 'আল্ট্রাসনোগ্রাম', 'আল্ট্রাসাউন্ড', 'ইউএসজি',
        ];

        if ($selectedIsRadiologyCategory) {
            $query->where(function ($radiologyGuardQuery) use ($normalizeCategorySql, $normalizeItemNameSql, $radiologyConflictMatchers) {
                $radiologyGuardQuery->where(function ($matchedQuery) use ($normalizeCategorySql, $normalizeItemNameSql, $radiologyConflictMatchers) {
                    foreach ($radiologyConflictMatchers as $matcher) {
                        $matchedQuery->whereRaw("{$normalizeCategorySql} != ?", [$matcher]);
                        $matchedQuery->whereRaw("{$normalizeItemNameSql} NOT LIKE ?", ['%' . $matcher . '%']);
                    }
                });
                $radiologyGuardQuery->orWhereRaw("{$normalizeCategorySql} = ''");
            });
        }

            if (Schema::hasTable('testcategories')) {
                $selectedMasterCategory = DB::table('testcategories')
                    ->whereIn(
                        DB::raw("LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(name), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))"),
                        $categoryMatchers
                    );
                if (Schema::hasColumn('testcategories', 'deleted_at')) {
                    $selectedMasterCategory->whereNull('deleted_at');
                }
                if (Schema::hasColumn('testcategories', 'status')) {
                    $selectedMasterCategory->where('status', '!=', 'Deleted');
                }
                if ($selectedMasterCategory->exists()) {
                    $categoryMasterMatchFound = true;
                }
            }

            if (Schema::hasTable('main_categories')) {
                $selectedMainCategory = DB::table('main_categories')
                    ->whereIn(
                        DB::raw("LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(name), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))"),
                        $categoryMatchers
                    );
                if (Schema::hasColumn('main_categories', 'deleted_at')) {
                    $selectedMainCategory->whereNull('deleted_at');
                }
                if (Schema::hasColumn('main_categories', 'status')) {
                    $selectedMainCategory->where('status', '!=', 'Deleted');
                }
                if ($selectedMainCategory->exists()) {
                    $categoryMasterMatchFound = true;
                }
            }

            if (!$categoryMasterMatchFound) {
                $query->whereRaw('1 = 0');
                return;
            }

            $normalizeSqlColumn = static function (string $column): string {
                return "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM({$column}), ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))";
            };
            $radiologyCategoryAliases = [
                'radiology', 'xray', 'ct', 'mri', 'ultrasound',
                'ultrasonogram', 'ultrasonography', 'sonography', 'usg', 'sono',
            ];

            $query->where(function ($categoryQuery) use (
                $categoryMatchers,
                $normalizeCategorySql,
                $normalizeSqlColumn,
                $selectedIsRadiologyCategory,
                $radiologyCategoryAliases
            ) {
                $categoryQuery->where(function ($exactQuery) use (
                    $categoryMatchers,
                    $normalizeCategorySql,
                    $selectedIsRadiologyCategory,
                    $radiologyCategoryAliases,
                    $normalizeSqlColumn
                ) {
                    foreach ($categoryMatchers as $matcher) {
                        $exactQuery->orWhereRaw("{$normalizeCategorySql} = ?", [$matcher]);
                    }

                    if (
                        $selectedIsRadiologyCategory
                        && Schema::hasTable('tests')
                        && Schema::hasColumn('bill_items', 'item_id')
                        && Schema::hasColumn('tests', 'category_type')
                    ) {
                        $testCategoryTypeSql = $normalizeSqlColumn('tests.category_type');
                        $exactQuery->whereNotExists(function ($testTypeQuery) use (
                            $radiologyCategoryAliases,
                            $testCategoryTypeSql
                        ) {
                            $testTypeQuery->selectRaw('1')
                                ->from('tests')
                                ->whereColumn('tests.id', 'bill_items.item_id')
                                ->whereNotIn(DB::raw($testCategoryTypeSql), $radiologyCategoryAliases);
                        });
                    }
                });

                if (!Schema::hasTable('tests') || !Schema::hasColumn('bill_items', 'item_id')) {
                    return;
                }

                $hasTestCategoryRelation = Schema::hasTable('testcategories')
                    && Schema::hasColumn('tests', 'test_category_id');
                $hasMainCategoryRelation = Schema::hasTable('main_categories')
                    && Schema::hasColumn('tests', 'main_category_id');
                $hasCategoryType = Schema::hasColumn('tests', 'category_type');

                if (
                    !$hasCategoryType
                    || (!$hasTestCategoryRelation && !$hasMainCategoryRelation)
                ) {
                    return;
                }

                $testCategoryTypeSql = $normalizeSqlColumn('tests.category_type');
                $billItemCategorySql = $normalizeSqlColumn('bill_items.category');

                $categoryQuery->orWhereExists(function ($testQuery) use (
                    $categoryMatchers,
                    $normalizeSqlColumn,
                    $hasTestCategoryRelation,
                    $hasMainCategoryRelation,
                    $selectedIsRadiologyCategory,
                    $testCategoryTypeSql,
                    $billItemCategorySql,
                    $radiologyCategoryAliases
                ) {
                    $testQuery->selectRaw('1')
                        ->from('tests')
                        ->whereColumn('tests.id', 'bill_items.item_id')
                        ->where(function ($itemTypeQuery) use (
                            $testCategoryTypeSql,
                            $billItemCategorySql,
                            $radiologyCategoryAliases
                        ) {
                            $itemTypeQuery->whereRaw("{$testCategoryTypeSql} = {$billItemCategorySql}")
                                ->orWhere(function ($radiologyTypeQuery) use (
                                    $testCategoryTypeSql,
                                    $billItemCategorySql,
                                    $radiologyCategoryAliases
                                ) {
                                    $radiologyTypeQuery
                                        ->whereIn(DB::raw($testCategoryTypeSql), $radiologyCategoryAliases)
                                        ->whereIn(DB::raw($billItemCategorySql), $radiologyCategoryAliases);
                                });
                        })
                        ->when($selectedIsRadiologyCategory, function ($radiologyTestQuery) use (
                            $testCategoryTypeSql,
                            $radiologyCategoryAliases
                        ) {
                            $radiologyTestQuery->whereIn(
                                DB::raw($testCategoryTypeSql),
                                $radiologyCategoryAliases
                            );
                        })
                        ->where(function ($testCategoryQuery) use ($categoryMatchers, $normalizeSqlColumn, $hasTestCategoryRelation, $hasMainCategoryRelation) {
                            if ($hasTestCategoryRelation) {
                                $testCategoryQuery->whereExists(
                                    function ($categoryNameQuery) use ($categoryMatchers, $normalizeSqlColumn) {
                                        $categoryNameQuery->selectRaw('1')
                                            ->from('testcategories')
                                            ->whereColumn('testcategories.id', 'tests.test_category_id')
                                            ->whereIn(
                                                DB::raw($normalizeSqlColumn('testcategories.name')),
                                                $categoryMatchers
                                            );

                                        if (Schema::hasColumn('testcategories', 'deleted_at')) {
                                            $categoryNameQuery->whereNull('testcategories.deleted_at');
                                        }
                                        if (Schema::hasColumn('testcategories', 'status')) {
                                            $categoryNameQuery->where('testcategories.status', '!=', 'Deleted');
                                        }
                                    }
                                );
                            }

                            if ($hasMainCategoryRelation) {
                                $mainCategoryMatch = function ($mainCategoryNameQuery) use ($categoryMatchers, $normalizeSqlColumn) {
                                    $mainCategoryNameQuery->selectRaw('1')
                                        ->from('main_categories')
                                        ->whereColumn('main_categories.id', 'tests.main_category_id')
                                        ->whereIn(
                                            DB::raw($normalizeSqlColumn('main_categories.name')),
                                            $categoryMatchers
                                        );

                                    if (Schema::hasColumn('main_categories', 'deleted_at')) {
                                        $mainCategoryNameQuery->whereNull('main_categories.deleted_at');
                                    }
                                    if (Schema::hasColumn('main_categories', 'status')) {
                                        $mainCategoryNameQuery->where('main_categories.status', '!=', 'Deleted');
                                    }
                                };

                                if ($hasTestCategoryRelation) {
                                    $testCategoryQuery->orWhereExists($mainCategoryMatch);
                                } else {
                                    $testCategoryQuery->whereExists($mainCategoryMatch);
                                }
                            }
                        });
                });
            });
        };
        $applyIncomeBillingFilters = function ($query) {
            if (Schema::hasColumn('billings', 'deleted_at')) {
                $query->whereNull('billings.deleted_at');
            }

            if (Schema::hasColumn('billings', 'status')) {
                $query->where('billings.status', 'Active');
            }

            if (
                Schema::hasColumn('billings', 'payment_status')
                && Schema::hasColumn('billings', 'due_amount')
                && Schema::hasColumn('billings', 'paid_amt')
            ) {
                $query->where(function ($paymentQuery) {
                    $paymentQuery->where('billings.payment_status', '!=', 'Pending')
                        ->orWhere('billings.due_amount', '<=', 0)
                        ->orWhere('billings.paid_amt', '>', 0);
                });
            }
        };

        $resolveAdminDisplayName = function ($row, $hasFirst, $hasLast, $hasName) {
            $first = $hasFirst ? trim((string) ($row->first_name ?? '')) : '';
            $last = $hasLast ? trim((string) ($row->last_name ?? '')) : '';
            $full = trim($first . ' ' . $last);

            if ($full !== '') {
                return $full;
            }

            if ($hasName) {
                return trim((string) ($row->name ?? ''));
            }

            return '';
        };

        $applyItemSearch = function ($query, string $searchTerm) {
            $searchTerm = trim($searchTerm);
            $normalizedSearchTerm = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $searchTerm));

            $query->where(function ($searchQuery) use ($searchTerm, $normalizedSearchTerm) {
                $searchQuery->where('bill_items.item_name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('bill_items.category', 'like', '%' . $searchTerm . '%');

                if ($normalizedSearchTerm !== '') {
                    $normalizedCategory = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(bill_items.category, ' ', ''), '-', ''), '_', ''), '/', ''), '.', ''))";
                    $searchQuery->orWhereRaw("{$normalizedCategory} like ?", ['%' . $normalizedSearchTerm . '%']);

                    $categoryAliases = [
                        'ultrasound' => ['ultrasound', 'ultrasonogram', 'ultrasonography', 'usg', 'sono'],
                        'radiology' => ['radiology', 'xray', 'ct', 'mri'],
                        'pathology' => ['pathology', 'pathological', 'lab', 'laboratory'],
                        'ecg' => ['ecg', 'ekg'],
                    ];
                    foreach ($categoryAliases as $canonical => $aliases) {
                        if (in_array($normalizedSearchTerm, $aliases, true)) {
                            $searchQuery->orWhereIn(DB::raw($normalizedCategory), $aliases);
                            if ($normalizedSearchTerm !== $canonical) {
                                $searchQuery->orWhereRaw("{$normalizedCategory} like ?", ['%' . $canonical . '%']);
                            }
                        }
                    }
                }
            });
        };

        $hasDoctorMatch = false;
        if (
            in_array($mode, [null, '', 'doctor', 'test'], true)
            && is_string($term)
            && trim($term) !== ''
        ) {
            $doctorMatchQuery = DB::table('billings')
                ->where('billings.doctor_name', 'like', '%' . trim($term) . '%')
                ->where('billings.case_number', 'not like', 'IPD-%');
            $applyIncomeBillingFilters($doctorMatchQuery);
            $applyDateRange($doctorMatchQuery, 'billings.created_at');
            $hasDoctorMatch = $doctorMatchQuery->exists();
            if ($hasDoctorMatch) {
                $mode = 'doctor_patient';
            }
        }

        // Prefer matching bill items so item searches work without choosing a category.
        if (!$hasDoctorMatch && ($mode === null || $mode === '' || $mode === 'doctor' || $mode === 'doctor_daily') && is_string($term) && trim($term) !== '') {
            $normalizedTerm = trim($term);

            $testMatchQuery = DB::table('bill_items')
                ->join('billings', 'bill_items.billing_id', '=', 'billings.id');
            $applyActiveBillItems($testMatchQuery);
            $applyIncomeBillingFilters($testMatchQuery);
            $applyItemSearch($testMatchQuery, $normalizedTerm);
            $applyDateRange($testMatchQuery, 'billings.created_at');

            $hasTestMatch = (clone $testMatchQuery)->exists();

            if ($hasTestMatch) {
                $mode = 'test';
            }
        }

        if (
            ($mode === null || $mode === '' || $mode === 'doctor' || $mode === 'doctor_daily')
            && is_string($subcat)
            && trim($subcat) !== ''
            && (!is_string($term) || trim($term) === '')
        ) {
            $mode = 'test';
        }

        // If front-end requests a names list for a category, return JSON (used by dropdowns)
        if ($request->query('list')) {
            $category = $request->query('category') ?? 'all';
            $names = [];

            if ($category === 'categories') {
                $names = collect();

                if (Schema::hasTable('testcategories')) {
                    $masterCategoryQuery = DB::table('testcategories as category')
                        ->whereNotNull('category.name')
                        ->where('category.name', '!=', '');

                    if (Schema::hasColumn('testcategories', 'deleted_at')) {
                        $masterCategoryQuery->whereNull('category.deleted_at');
                    }
                    if (Schema::hasColumn('testcategories', 'status')) {
                        $masterCategoryQuery->where('category.status', '!=', 'Deleted');
                    }

                    $names = $masterCategoryQuery
                        ->selectRaw('category.name as name')
                        ->orderBy('category.name')
                        ->pluck('name');
                }

                if (Schema::hasTable('main_categories')) {
                    $mainCategoryQuery = DB::table('main_categories as category')
                        ->whereNotNull('category.name')
                        ->where('category.name', '!=', '');

                    if (Schema::hasColumn('main_categories', 'deleted_at')) {
                        $mainCategoryQuery->whereNull('category.deleted_at');
                    }
                    if (Schema::hasColumn('main_categories', 'status')) {
                        $mainCategoryQuery->where('category.status', '!=', 'Deleted');
                    }

                    $names = $names->merge(
                        $mainCategoryQuery
                            ->selectRaw('category.name as name')
                            ->orderBy('category.name')
                            ->pluck('name')
                    );
                }

                return response()->json([
                    'names' => $names
                        ->map(fn ($value) => trim((string) $value))
                        ->filter(fn ($value) => $value !== '')
                        ->unique(fn ($value) => Str::lower($value))
                        ->sortBy(fn ($value) => Str::lower($value))
                        ->values(),
                ]);
            }

            switch ($category) {
                case 'doctor':
                    $q = DB::table('billings')->whereNotNull('doctor_id')->where('billings.case_number', 'not like', 'IPD-%');
                    $applyDateRange($q, 'billings.created_at');
                    $names = $q->select(DB::raw("COALESCE(NULLIF(billings.doctor_name, ''), '') as name"))
                        ->distinct()->orderBy('name')->pluck('name')->filter()->unique()->values()->all();
                    break;

                case 'test':
                    $q = DB::table('bill_items')->leftJoin('billings', 'bill_items.billing_id', '=', 'billings.id');
                    $applyActiveBillItems($q);
                    if ($subcat) {
                        $q->where('bill_items.category', $subcat);
                    }
                    $applyDateRange($q, 'billings.created_at');
                    $names = $q->whereNotNull('bill_items.item_name')
                        ->select(DB::raw('bill_items.item_name as name'))
                        ->distinct()->orderBy('name')->pluck('name')->filter()->unique()->values()->all();
                    break;

                case 'technologist':
                case 'pathologist':
                    $idsQuery = DB::table('bill_items')->leftJoin('billings', 'bill_items.billing_id', '=', 'billings.id');
                    $applyActiveBillItems($idsQuery);
                    $applyDateRange($idsQuery, 'billings.created_at');
                    $ids = $idsQuery->whereNotNull('bill_items.reported_by')->pluck('bill_items.reported_by')->filter()->unique()->values()->all();
                    if (!empty($ids)) {
                        $adminHasName = Schema::hasColumn('admins', 'name');
                        $adminHasFirst = Schema::hasColumn('admins', 'first_name');
                        $adminHasLast = Schema::hasColumn('admins', 'last_name');
                        if ($adminHasFirst || $adminHasLast) {
                            $cols = [];
                            if ($adminHasFirst) $cols[] = 'first_name';
                            if ($adminHasLast) $cols[] = 'last_name';
                            if ($adminHasName) $cols[] = 'name';
                            $cols[] = 'id';
                            $rows = DB::table('admins')->whereIn('id', $ids)->get($cols);
                            foreach ($rows as $r) {
                                $display = $resolveAdminDisplayName($r, $adminHasFirst, $adminHasLast, $adminHasName);
                                if ($display !== '') {
                                    $names[] = $display;
                                }
                            }
                        } elseif ($adminHasName) {
                            $names = DB::table('admins')->whereIn('id', $ids)->pluck('name')->filter()->unique()->values()->all();
                        }
                    }
                    $names = array_values(array_filter(array_unique($names)));
                    break;

                case 'collector':
                    $idsQuery = DB::table('bill_items')->leftJoin('billings', 'bill_items.billing_id', '=', 'billings.id');
                    $applyActiveBillItems($idsQuery);
                    $applyDateRange($idsQuery, 'billings.created_at');
                    $ids = $idsQuery->whereNotNull('bill_items.sample_collected_by')->pluck('bill_items.sample_collected_by')->filter()->unique()->values()->all();
                    if (!empty($ids)) {
                        $adminHasName = Schema::hasColumn('admins', 'name');
                        $adminHasFirst = Schema::hasColumn('admins', 'first_name');
                        $adminHasLast = Schema::hasColumn('admins', 'last_name');
                        if ($adminHasFirst || $adminHasLast) {
                            $cols = [];
                            if ($adminHasFirst) $cols[] = 'first_name';
                            if ($adminHasLast) $cols[] = 'last_name';
                            if ($adminHasName) $cols[] = 'name';
                            $cols[] = 'id';
                            $rows = DB::table('admins')->whereIn('id', $ids)->get($cols);
                            foreach ($rows as $r) {
                                $display = $resolveAdminDisplayName($r, $adminHasFirst, $adminHasLast, $adminHasName);
                                if ($display !== '') {
                                    $names[] = $display;
                                }
                            }
                        } elseif ($adminHasName) {
                            $names = DB::table('admins')->whereIn('id', $ids)->pluck('name')->filter()->unique()->values()->all();
                        }
                    }
                    $names = array_values(array_filter(array_unique($names)));
                    break;

                case 'referrer':
                    $idsQuery = DB::table('billings');
                    $applyDateRange($idsQuery, 'billings.created_at');
                    $ids = $idsQuery->whereNotNull('billings.referrer_id')->pluck('billings.referrer_id')->filter()->unique()->values()->all();
                    if (!empty($ids)) {
                        $adminHasName = Schema::hasColumn('admins', 'name');
                        $adminHasFirst = Schema::hasColumn('admins', 'first_name');
                        $adminHasLast = Schema::hasColumn('admins', 'last_name');
                        if ($adminHasFirst || $adminHasLast) {
                            $cols = [];
                            if ($adminHasFirst) $cols[] = 'first_name';
                            if ($adminHasLast) $cols[] = 'last_name';
                            if ($adminHasName) $cols[] = 'name';
                            $cols[] = 'id';
                            $rows = DB::table('admins')->whereIn('id', $ids)->get($cols);
                            foreach ($rows as $r) {
                                $display = $resolveAdminDisplayName($r, $adminHasFirst, $adminHasLast, $adminHasName);
                                if ($display !== '') {
                                    $names[] = $display;
                                }
                            }
                        } elseif ($adminHasName) {
                            $names = DB::table('admins')->whereIn('id', $ids)->pluck('name')->filter()->unique()->values()->all();
                        }
                    }
                    $names = array_values(array_filter(array_unique($names)));
                    break;

                default:
                    $names = ['doctor', 'test', 'technologist', 'collector', 'referrer', 'radiology', 'pathology'];
                    break;
            }

            return response()->json(['names' => $names]);
        }

        if ($mode === 'doctor_patient') {
            $patientJoinAvailable = Schema::hasTable('patients') && Schema::hasColumn('billings', 'patient_id');
            $patientQuery = DB::table('bill_items')
                ->join('billings', 'bill_items.billing_id', '=', 'billings.id');

            if ($patientJoinAvailable) {
                $patientQuery->leftJoin('patients', 'billings.patient_id', '=', 'patients.id');
            }

            $applyActiveBillItems($patientQuery);
            $applyIncomeBillingFilters($patientQuery);
            $applyDateRange($patientQuery, 'billings.created_at');
            $applyCategoryFilter($patientQuery);
            $patientQuery
                ->where('billings.case_number', 'not like', 'IPD-%')
                ->where('billings.doctor_name', 'like', '%' . trim((string) $term) . '%');

            if (Schema::hasColumn('billings', 'patient_name') && $patientJoinAvailable && Schema::hasColumn('patients', 'name')) {
                $patientNameExpression = "COALESCE(NULLIF(billings.patient_name, ''), NULLIF(patients.name, ''), '')";
            } elseif (Schema::hasColumn('billings', 'patient_name')) {
                $patientNameExpression = "COALESCE(NULLIF(billings.patient_name, ''), '')";
            } elseif ($patientJoinAvailable && Schema::hasColumn('patients', 'name')) {
                $patientNameExpression = "COALESCE(NULLIF(patients.name, ''), '')";
            } elseif ($patientJoinAvailable && (Schema::hasColumn('patients', 'first_name') || Schema::hasColumn('patients', 'last_name'))) {
                $firstName = Schema::hasColumn('patients', 'first_name') ? 'patients.first_name' : "''";
                $lastName = Schema::hasColumn('patients', 'last_name') ? 'patients.last_name' : "''";
                $patientNameExpression = "COALESCE(NULLIF(CONCAT_WS(' ', {$firstName}, {$lastName}), ''), '')";
            } else {
                $patientNameExpression = "''";
            }

            $quantityExpression = Schema::hasColumn('bill_items', 'quantity')
                ? 'COALESCE(bill_items.quantity, 1)'
                : '1';
            $amountExpression = Schema::hasColumn('bill_items', 'net_amount')
                ? 'COALESCE(bill_items.net_amount, 0)'
                : (Schema::hasColumn('bill_items', 'total_amount')
                    ? 'COALESCE(bill_items.total_amount, 0)'
                    : (Schema::hasColumn('bill_items', 'unit_price')
                        ? "COALESCE(bill_items.unit_price, 0) * {$quantityExpression}"
                        : '0'));

            $itemSelects = [
                'billings.id as billing_id',
                'billings.doctor_name',
                'billings.case_number',
                'bill_items.item_name',
                'bill_items.category',
                DB::raw("{$patientNameExpression} as patient_name"),
                DB::raw($patientJoinAvailable ? 'patients.id as patient_id' : (Schema::hasColumn('billings', 'patient_id') ? 'billings.patient_id as patient_id' : 'NULL as patient_id')),
                DB::raw("{$quantityExpression} as quantity"),
                DB::raw("{$amountExpression} as price"),
                DB::raw('DATE(billings.created_at) as billing_date'),
            ];
            if (Schema::hasColumn('billings', 'bill_number')) {
                $itemSelects[] = 'billings.bill_number';
            }
            $itemRows = $patientQuery
                ->select($itemSelects)
                ->orderByDesc('billings.created_at')
                ->orderBy('billings.case_number')
                ->orderBy('bill_items.item_name')
                ->get();

            $groupedRows = $itemRows
                ->groupBy('billing_id')
                ->map(function ($billingItems) {
                    $firstItem = $billingItems->first();

                    return (object) [
                        'billing_id' => $firstItem->billing_id,
                        'bill_number' => $firstItem->bill_number ?? null,
                        'doctor_name' => $firstItem->doctor_name,
                        'case_number' => $firstItem->case_number,
                        'patient_id' => $firstItem->patient_id,
                        'patient_name' => $firstItem->patient_name,
                        'billing_date' => $firstItem->billing_date,
                        'category' => $billingItems->pluck('category')->filter()->unique()->implode(', '),
                        'item_names' => $billingItems->pluck('item_name')->filter()->values(),
                        'quantity' => (float) $billingItems->sum(fn ($item) => (float) $item->quantity),
                        'price' => round((float) $billingItems->sum(fn ($item) => (float) $item->price), 2),
                    ];
                })
                ->values();

            $totalTests = (float) $groupedRows->sum('quantity');
            $totalAmount = (float) $groupedRows->sum('price');
            $totalPatients = (int) $groupedRows->count();
            $currentPage = max(1, (int) $request->query('page', 1));
            $paginator = new LengthAwarePaginator(
                $groupedRows->slice(($currentPage - 1) * $perPage, $perPage)->values(),
                $totalPatients,
                $perPage,
                $currentPage,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return Inertia::render('Backend/Reports/DoctorSummary', [
                'pageTitle' => fn () => 'Doctor Patient and Test Details',
                'companyName' => $resolveCompanyName,
                'filters' => fn () => $request->only('q', 'from', 'to', 'numOfData', 'mode', 'subcat'),
                'mode' => fn () => 'doctor_patient',
                'term' => fn () => trim((string) $term),
                'meta' => fn () => [
                    'total_cases' => $totalPatients,
                    'total_items' => $totalTests,
                    'grand_total' => round($totalAmount, 2),
                ],
                'datas' => fn () => $paginator,
            ]);
        }

        // MODE: search by test name (list case ids where a test exists + totals)
        if ($mode === 'test') {
            $itemsQuery = DB::table('bill_items')
                ->join('billings', 'bill_items.billing_id', '=', 'billings.id')
                ->leftJoin('patients', 'billings.patient_id', '=', 'patients.id')
                ->when($term, function ($q) use ($term, $applyItemSearch) {
                    $applyItemSearch($q, $term);
                })
                ->when(true, function ($q) use ($applyDateRange) {
                    $applyDateRange($q, 'billings.created_at');
                });
            $applyActiveBillItems($itemsQuery);
            $applyIncomeBillingFilters($itemsQuery);
            $applyCategoryFilter($itemsQuery);

            $selects = ['billings.id', 'billings.id as billing_id', 'billings.case_number', 'billings.created_at as billing_date', 'bill_items.item_name', 'bill_items.id as bill_item_id'];

            if ($hasBillNumberColumn) {
                $selects[] = 'billings.bill_number';
            } else {
                $selects[] = DB::raw("COALESCE(NULLIF(billings.case_number, ''), '') as bill_number");
            }

            if (Schema::hasColumn('billings', 'patient_name')) {
                $selects[] = 'billings.patient_name';
            } else {
                if (Schema::hasColumn('patients', 'name')) {
                    $selects[] = DB::raw("COALESCE(NULLIF(patients.name, ''), '') as patient_name");
                } elseif (Schema::hasColumn('patients', 'first_name') || Schema::hasColumn('patients', 'last_name')) {
                    $first = Schema::hasColumn('patients', 'first_name') ? 'patients.first_name' : "''";
                    $last = Schema::hasColumn('patients', 'last_name') ? 'patients.last_name' : "''";
                    $selects[] = DB::raw("COALESCE(NULLIF(CONCAT_WS(' ', $first, $last), ''), '') as patient_name");
                } else {
                    $selects[] = DB::raw("'' as patient_name");
                }
            }

            if (Schema::hasColumn('billings', 'patient_mobile')) {
                $selects[] = 'billings.patient_mobile';
            } else {
                if (Schema::hasColumn('patients', 'mobile')) {
                    $selects[] = DB::raw("COALESCE(NULLIF(patients.mobile, ''), '') as patient_mobile");
                } elseif (Schema::hasColumn('patients', 'phone')) {
                    $selects[] = DB::raw("COALESCE(NULLIF(patients.phone, ''), '') as patient_mobile");
                } else {
                    $selects[] = DB::raw("'' as patient_mobile");
                }
            }

            if (Schema::hasColumn('bill_items', 'quantity')) {
                $selects[] = 'bill_items.quantity as quantity';
            } else {
                $selects[] = DB::raw('1 as quantity');
            }
            if (Schema::hasColumn('bill_items', 'category')) {
                $selects[] = 'bill_items.category';
            }
            if (Schema::hasColumn('bill_items', 'total_amount')) {
                $selects[] = 'bill_items.total_amount';
            }

            // Prefer the billed net amount; unit prices must be multiplied by quantity.
            if (Schema::hasColumn('bill_items', 'net_amount')) {
                $selects[] = 'bill_items.net_amount as price';
            } elseif (Schema::hasColumn('bill_items', 'total_amount')) {
                $selects[] = 'bill_items.total_amount as price';
            } elseif (Schema::hasColumn('bill_items', 'unit_price')) {
                if (Schema::hasColumn('bill_items', 'quantity')) {
                    $selects[] = DB::raw('bill_items.unit_price * bill_items.quantity as price');
                } else {
                    $selects[] = 'bill_items.unit_price as price';
                }
            } else {
                $selects[] = DB::raw("0 as price");
            }

            $itemsQuery = $itemsQuery->select($selects)->orderByDesc('billings.created_at');


            $items = $itemsQuery->get();

            $isDisposableItem = function ($item) {
                $category = strtolower(trim((string) ($item->category ?? '')));
                if ($category === 'disposable') {
                    return true;
                }

                if ($category !== 'pathology') {
                    return false;
                }

                $name = strtolower((string) ($item->item_name ?? ''));
                foreach (['disposable', 'tube', 'butterfly', 'needle'] as $keyword) {
                    if (str_contains($name, $keyword)) {
                        return true;
                    }
                }

                return false;
            };
            $items = $items->map(function ($item) use ($isDisposableItem) {
                if ($isDisposableItem($item) && isset($item->total_amount)) {
                    $item->price = (float) $item->total_amount;
                }

                return $item;
            });

            $netByBilling = $items->groupBy('billing_id')->map(function ($billingItems) use ($isDisposableItem) {
                return (float) $billingItems
                    ->reject($isDisposableItem)
                    ->sum(fn ($item) => (float) ($item->price ?? 0));
            });
            $matchedAmountByBilling = $items->groupBy('billing_id')->map(function ($billingItems) {
                return (float) $billingItems->sum(fn ($item) => (float) ($item->price ?? 0));
            });
            $billingIds = $netByBilling->keys()->filter()->values();
            $allNetByBilling = collect();

            if ($billingIds->isNotEmpty()) {
                $discountExpression = Schema::hasColumn('bill_items', 'discount')
                    ? 'COALESCE(discount, 0)'
                    : '0';
                if (Schema::hasColumn('bill_items', 'net_amount')) {
                    $netExpression = Schema::hasColumn('bill_items', 'total_amount')
                        ? "COALESCE(net_amount, GREATEST(0, total_amount - {$discountExpression}))"
                        : 'COALESCE(net_amount, 0)';
                } elseif (Schema::hasColumn('bill_items', 'total_amount')) {
                    $netExpression = "GREATEST(0, total_amount - {$discountExpression})";
                } elseif (Schema::hasColumn('bill_items', 'unit_price') && Schema::hasColumn('bill_items', 'quantity')) {
                    $netExpression = 'unit_price * quantity';
                } elseif (Schema::hasColumn('bill_items', 'unit_price')) {
                    $netExpression = 'unit_price';
                } else {
                    $netExpression = '0';
                }

                $allNetQuery = DB::table('bill_items')
                    ->whereIn('billing_id', $billingIds)
                    ->selectRaw("billing_id, category, item_name, {$netExpression} as net_amount");
                $applyActiveBillItems($allNetQuery);
                $allNetByBilling = $allNetQuery->get()
                    ->reject($isDisposableItem)
                    ->groupBy('billing_id')
                    ->map(fn ($billingItems) => (float) $billingItems->sum('net_amount'));
            }

            $extraDiscountByBilling = Schema::hasColumn('billings', 'extra_flat_discount')
                ? DB::table('billings')->whereIn('id', $billingIds)->pluck('extra_flat_discount', 'id')
                : collect();
            $allocatedExtraDiscountByBilling = $netByBilling->map(function ($matchedNet, $billingId) use ($allNetByBilling, $extraDiscountByBilling) {
                $allNet = (float) ($allNetByBilling->get($billingId) ?? 0);
                $extraDiscount = max(0, (float) ($extraDiscountByBilling->get($billingId) ?? 0));

                if ($allNet <= 0 || $extraDiscount <= 0) {
                    return 0;
                }

                return min($matchedNet, $extraDiscount * ($matchedNet / $allNet));
            });

            $dueAmount = 0.0;
            $dueCases = 0;
            if (
                $billingIds->isNotEmpty()
                && Schema::hasColumn('billings', 'due_amount')
            ) {
                $dueQuery = DB::table('billings')
                    ->whereIn('billings.id', $billingIds)
                    ->where('billings.due_amount', '>', 0);
                $applyIncomeBillingFilters($dueQuery);
                $applyDateRange($dueQuery, 'billings.created_at');

                $dueAmount = (float) $dueQuery->sum('billings.due_amount');
                $dueCases = Schema::hasColumn('billings', 'case_number')
                    ? (int) $dueQuery->distinct('billings.case_number')->count('billings.case_number')
                    : (int) $dueQuery->count();
            }

            $distinctBills = $items->map(function ($i) {
                return (object) [
                    'id' => $i->id,
                    'billing_id' => $i->billing_id ?? $i->id,
                    'bill_number' => $i->bill_number ?? null,
                    'case_number' => $i->case_number,
                    'patient_name' => $i->patient_name,
                    'patient_mobile' => $i->patient_mobile,
                    'billing_date' => $i->billing_date ?? null,
                ];
            })->unique('id')->values();

            $total_cases = $distinctBills->count();
            $total_items = $items->sum(function ($item) {
                return (float) ($item->quantity ?? 1);
            });

            $grand_total = round(
                $matchedAmountByBilling->sum() - $allocatedExtraDiscountByBilling->sum(),
                2
            );

            $effectivePerPage = $allPerPage ? max($distinctBills->count(), 1) : $perPage;
            $offset = max(0, ($page - 1) * $effectivePerPage);
            $pageRows = $distinctBills->slice($offset, $effectivePerPage)->values()->map(function ($r) use ($items, $allocatedExtraDiscountByBilling) {
                $matchedItems = $items->filter(function ($it) use ($r) {
                    return $it->id == $r->id;
                });

                $matched = $matchedItems->pluck('item_name')->unique()->values();
                $qty = $matchedItems->sum(function ($item) {
                    return (float) ($item->quantity ?? 1);
                });
                $sumPrice = $matchedItems->reduce(function ($carry, $it) {
                    return $carry + (float) ($it->price ?? 0);
                }, 0);
                $sumPrice -= (float) ($allocatedExtraDiscountByBilling->get($r->billing_id) ?? 0);

                return (object) [
                    'id' => $r->id,
                    'billing_id' => $r->billing_id ?? $r->id,
                    'bill_number' => $r->bill_number ?? null,
                    'case_number' => $r->case_number,
                    'patient_name' => $r->patient_name,
                    'patient_mobile' => $r->patient_mobile,
                    'billing_date' => $r->billing_date ?? null,
                    'item_name' => $matched->first() ?? ($r->item_name ?? null),
                    'matched_tests' => $matched,
                    'quantity' => $qty,
                    'price' => round(max(0, $sumPrice), 2),
                ];
            });

            $paginator = regeneratePagination($pageRows, $distinctBills->count(), $effectivePerPage, $page);

            return Inertia::render('Backend/Reports/DoctorSummary', [
                'pageTitle' => fn () => 'Test Search Results',
                'companyName' => $resolveCompanyName,
                'filters' => fn () => $request->only('q', 'from', 'to', 'numOfData', 'mode', 'subcat'),
                'mode' => fn () => 'test',
                'term' => fn () => $term,
                'meta' => fn () => [
                    'total_cases' => $total_cases,
                    'total_items' => $total_items,
                    'grand_total' => $grand_total,
                    'due_amount' => round($dueAmount, 2),
                    'due_cases' => $dueCases,
                ],
                'datas' => fn () => $paginator,
            ]);
        }

        // MODE: search by referrer (referrer doctor)
        if ($mode === 'referrer') {
            $q = DB::table('billings')
                ->leftJoin('admins', 'billings.referrer_id', '=', 'admins.id')
                ->leftJoin('patients', 'billings.patient_id', '=', 'patients.id')
                ->when(true, function ($q) use ($applyDateRange) {
                    $applyDateRange($q, 'billings.created_at');
                });

            // Safely resolve referrer/admin ids for term filtering and compute names in PHP
            $referrerSelects = ['billings.id', 'billings.case_number', 'billings.referrer_id', 'billings.created_at as billing_date'];
            if ($hasBillNumberColumn) {
                $referrerSelects[] = 'billings.bill_number';
            } else {
                $referrerSelects[] = DB::raw("COALESCE(NULLIF(billings.case_number, ''), '') as bill_number");
            }
            $adminHasName = Schema::hasColumn('admins', 'name');
            $adminHasFirst = Schema::hasColumn('admins', 'first_name');
            $adminHasLast = Schema::hasColumn('admins', 'last_name');

            if ($term) {
                $matchedAdminIds = [];
                try {
                        // Prefer searching by first_name/last_name concatenation to avoid
                        // relying on a non-existent `admins.name` column in some schemas.
                        if ($adminHasFirst || $adminHasLast) {
                            $parts = [];
                            if ($adminHasFirst) $parts[] = 'admins.first_name';
                            if ($adminHasLast) $parts[] = 'admins.last_name';
                            $concat = "CONCAT_WS(' ', " . implode(', ', $parts) . ")";
                            $matchedAdminIds = DB::table('admins')->whereRaw("{$concat} like ?", ["%{$term}%"])->pluck('id')->all();
                        } elseif ($adminHasName) {
                            $matchedAdminIds = DB::table('admins')->where('name', 'like', '%' . $term . '%')->pluck('id')->all();
                        }
                } catch (\Throwable $e) {
                    $matchedAdminIds = [];
                }

                if (!empty($matchedAdminIds)) {
                    $q->whereIn('billings.referrer_id', $matchedAdminIds);
                } else {
                    $q->whereRaw('0 = 1');
                }
            }

            $selects = $referrerSelects;

            if (Schema::hasColumn('billings', 'patient_name')) {
                $selects[] = 'billings.patient_name';
            } else {
                if (Schema::hasColumn('patients', 'name')) {
                    $selects[] = DB::raw("COALESCE(NULLIF(patients.name, ''), '') as patient_name");
                } else {
                    $selects[] = DB::raw("COALESCE(NULLIF(CONCAT_WS(' ', patients.first_name, patients.last_name), ''), '') as patient_name");
                }
            }

            if (Schema::hasColumn('billings', 'patient_mobile')) {
                $selects[] = 'billings.patient_mobile';
            } else {
                if (Schema::hasColumn('patients', 'mobile')) {
                    $selects[] = DB::raw("COALESCE(NULLIF(patients.mobile, ''), '') as patient_mobile");
                } else {
                    $selects[] = DB::raw("COALESCE(NULLIF(patients.phone, ''), '') as patient_mobile");
                }
            }

            $q = $q->select($selects)->orderByDesc('billings.created_at')->get();

            // Resolve referrer display names in PHP
            $referrerIds = $q->pluck('referrer_id')->filter()->unique()->values()->all();
            $refMap = [];
            if (!empty($referrerIds)) {
                try {
                    // Always prefer first_name/last_name if available
                    if ($adminHasFirst || $adminHasLast) {
                        $cols = [];
                        if ($adminHasFirst) $cols[] = 'first_name';
                        if ($adminHasLast) $cols[] = 'last_name';
                        if ($adminHasName) $cols[] = 'name';
                        $cols[] = 'id';
                        $rows = DB::table('admins')->whereIn('id', $referrerIds)->get($cols);
                        foreach ($rows as $r) {
                            $display = $resolveAdminDisplayName($r, $adminHasFirst, $adminHasLast, $adminHasName);
                            if ($display !== '') {
                                $refMap[$r->id] = $display;
                            }
                        }
                    } elseif ($adminHasName) {
                        $rows = DB::table('admins')->whereIn('id', $referrerIds)->get(['id', 'name']);
                        foreach ($rows as $r) {
                            $refMap[$r->id] = $r->name ?? '';
                        }
                    }
                } catch (\Throwable $e) {
                    $refMap = [];
                }
            }

            $q = $q->map(function ($it) use ($refMap) {
                $it->referrer_name = $refMap[$it->referrer_id] ?? '';
                return $it;
            });

            $distinct = $q->unique('id')->values();
            $total_cases = $distinct->count();

            $effectivePerPage = $allPerPage ? max($distinct->count(), 1) : $perPage;
            $offset = max(0, ($page - 1) * $effectivePerPage);
            $pageRows = $distinct->slice($offset, $effectivePerPage)->values();

            $paginator = regeneratePagination($pageRows, $distinct->count(), $effectivePerPage, $page);

            return Inertia::render('Backend/Reports/DoctorSummary', [
                'pageTitle' => fn () => 'Referrer Search Results',
                'companyName' => $resolveCompanyName,
                'filters' => fn () => $request->only('q', 'from', 'to', 'numOfData', 'mode'),
                'mode' => fn () => 'referrer',
                'term' => fn () => $term,
                'meta' => fn () => ['total_cases' => $total_cases],
                'datas' => fn () => $paginator,
            ]);
        }

        // MODE: reported_by (technologist/pathologist)
        if ($mode === 'technologist' || $mode === 'pathologist') {
            $itemsQuery = DB::table('bill_items')
                ->leftJoin('admins', 'bill_items.reported_by', '=', 'admins.id')
                ->leftJoin('billings', 'bill_items.billing_id', '=', 'billings.id')
                ->leftJoin('patients', 'billings.patient_id', '=', 'patients.id');
            $applyActiveBillItems($itemsQuery);

            // Safely resolve admin IDs matching the search term to avoid referencing
            // potentially-missing admin name columns in SQL. This prevents SQL errors
            // when different installations have different admin column schemas.
            $adminHasName = Schema::hasColumn('admins', 'name');
            $adminHasFirst = Schema::hasColumn('admins', 'first_name');
            $adminHasLast = Schema::hasColumn('admins', 'last_name');

            if ($term) {
                $matchedAdminIds = [];
                try {
                    if ($adminHasName || $adminHasFirst || $adminHasLast) {
                        $like = '%' . $term . '%';
                        $matchedAdminIds = DB::table('admins')
                            ->where(function ($q) use ($like, $adminHasName, $adminHasFirst, $adminHasLast) {
                                if ($adminHasName) {
                                    $q->orWhere('admins.name', 'like', $like);
                                }
                                if ($adminHasFirst) {
                                    $q->orWhere('admins.first_name', 'like', $like);
                                }
                                if ($adminHasLast) {
                                    $q->orWhere('admins.last_name', 'like', $like);
                                }
                                if ($adminHasFirst || $adminHasLast) {
                                    $parts = [];
                                    if ($adminHasFirst) $parts[] = 'admins.first_name';
                                    if ($adminHasLast) $parts[] = 'admins.last_name';
                                    $concat = "TRIM(CONCAT_WS(' ', " . implode(', ', $parts) . "))";
                                    $q->orWhereRaw("{$concat} like ?", [$like]);
                                }
                            })
                            ->pluck('id')
                            ->all();
                    }
                } catch (\Throwable $e) {
                    $matchedAdminIds = [];
                }

                // Allow searching by reporter name OR test name / case number / patient name.
                // This prevents empty results when user searches for a test like "rbs".
                $itemsQuery->where(function ($q) use ($term, $matchedAdminIds) {
                    if (!empty($matchedAdminIds)) {
                        $q->whereIn('bill_items.reported_by', $matchedAdminIds);
                    }

                    $q->orWhere('bill_items.item_name', 'like', '%' . $term . '%')
                      ->orWhere('billings.case_number', 'like', '%' . $term . '%');

                    if (Schema::hasColumn('billings', 'patient_name')) {
                        $q->orWhere('billings.patient_name', 'like', '%' . $term . '%');
                    } else {
                        if (Schema::hasColumn('patients', 'name')) {
                            $q->orWhere('patients.name', 'like', '%' . $term . '%');
                        } else {
                            $parts = [];
                            if (Schema::hasColumn('patients', 'first_name')) $parts[] = 'patients.first_name';
                            if (Schema::hasColumn('patients', 'last_name')) $parts[] = 'patients.last_name';
                            if (!empty($parts)) {
                                $concat = "CONCAT_WS(' ', " . implode(', ', $parts) . ")";
                                $q->orWhereRaw("{$concat} like ?", ["%{$term}%"]);
                            }
                        }
                    }
                });
            }

            $itemsQuery->when(true, function ($q) use ($from, $to, $term) {
                    if ($from && $to) {
                        $q->whereDate('bill_items.reported_at', '>=', $from)
                          ->whereDate('bill_items.reported_at', '<=', $to);
                    } elseif ($from) {
                        $q->whereDate('bill_items.reported_at', '>=', $from);
                    } elseif ($to) {
                        $q->whereDate('bill_items.reported_at', '<=', $to);
                    } elseif (!$term) {
                        $q->whereDate('bill_items.reported_at', now()->toDateString());
                    }
                })
                ->whereNotNull('bill_items.reported_at');

            // Always select the reporter id (from bill_items) and compute display name in PHP
            // to avoid SQL errors due to missing admin name columns.
            $selects = ['bill_items.id', 'bill_items.item_name', 'bill_items.reported_at', 'billings.id as billing_id', 'billings.case_number', 'billings.created_at as billing_date', 'bill_items.reported_by as reporter_id'];

            if ($hasBillNumberColumn) {
                $selects[] = 'billings.bill_number';
            } else {
                $selects[] = DB::raw("COALESCE(NULLIF(billings.case_number, ''), '') as bill_number");
            }

            // patient name fallback strategy
            if (Schema::hasColumn('billings', 'patient_name')) {
                $selects[] = 'billings.patient_name';
            } else {
                if (Schema::hasColumn('patients', 'name')) {
                    $selects[] = DB::raw("COALESCE(NULLIF(patients.name, ''), '') as patient_name");
                } elseif (Schema::hasColumn('patients', 'first_name') || Schema::hasColumn('patients', 'last_name')) {
                    $first = Schema::hasColumn('patients', 'first_name') ? 'patients.first_name' : "''";
                    $last = Schema::hasColumn('patients', 'last_name') ? 'patients.last_name' : "''";
                    $selects[] = DB::raw("COALESCE(NULLIF(CONCAT_WS(' ', $first, $last), ''), '') as patient_name");
                } else {
                    $selects[] = DB::raw("'' as patient_name");
                }
            }

            // patient mobile fallback
            if (Schema::hasColumn('billings', 'patient_mobile')) {
                $selects[] = 'billings.patient_mobile';
            } else {
                if (Schema::hasColumn('patients', 'mobile')) {
                    $selects[] = DB::raw("COALESCE(NULLIF(patients.mobile, ''), '') as patient_mobile");
                } elseif (Schema::hasColumn('patients', 'phone')) {
                    $selects[] = DB::raw("COALESCE(NULLIF(patients.phone, ''), '') as patient_mobile");
                } else {
                    $selects[] = DB::raw("'' as patient_mobile");
                }
            }

            // price selection (unit_price preferred)
            if (Schema::hasColumn('bill_items', 'unit_price')) {
                $selects[] = 'bill_items.unit_price as price';
            } elseif (Schema::hasColumn('bill_items', 'total_amount')) {
                $selects[] = 'bill_items.total_amount as price';
            } else {
                $selects[] = DB::raw("0 as price");
            }

            $items = $itemsQuery->select($selects)->orderByDesc('bill_items.reported_at')->get();

            // Resolve reporter display names in PHP to avoid SQL errors across schemas
            $reporterIds = $items->pluck('reporter_id')->filter()->unique()->values()->all();
            $adminMap = [];
            if (!empty($reporterIds)) {
                try {
                    if ($adminHasFirst || $adminHasLast) {
                        $cols = [];
                        if ($adminHasFirst) $cols[] = 'first_name';
                        if ($adminHasLast) $cols[] = 'last_name';
                        if ($adminHasName) $cols[] = 'name';
                        $cols[] = 'id';
                        $rows = DB::table('admins')->whereIn('id', $reporterIds)->get($cols);
                        foreach ($rows as $r) {
                            $display = $resolveAdminDisplayName($r, $adminHasFirst, $adminHasLast, $adminHasName);
                            if ($display !== '') {
                                $adminMap[$r->id] = $display;
                            }
                        }
                    } elseif ($adminHasName) {
                        $rows = DB::table('admins')->whereIn('id', $reporterIds)->get(['id', 'name']);
                        foreach ($rows as $r) {
                            $adminMap[$r->id] = $r->name ?? '';
                        }
                    }
                } catch (\Throwable $e) {
                    $adminMap = [];
                }
            }

            // Debug: log reporterIds and adminMap to help troubleshooting blank names
            try {
                @file_put_contents($debugLog, date('Y-m-d H:i:s') . " - reporterIds: " . json_encode($reporterIds) . PHP_EOL, FILE_APPEND);
                @file_put_contents($debugLog, date('Y-m-d H:i:s') . " - adminMap: " . json_encode($adminMap) . PHP_EOL, FILE_APPEND);
                @file_put_contents($debugLog, date('Y-m-d H:i:s') . " - sample items (first 5): " . json_encode($items->take(5)) . PHP_EOL, FILE_APPEND);
            } catch (\Throwable $e) {
                // ignore logging failures
            }

            $items = $items->map(function ($it) use ($adminMap) {
                $it->reporter_name = $adminMap[$it->reporter_id] ?? '';
                return $it;
            });

            $total_reports = $items->count();
            $distinctCases = $items->pluck('case_number')->filter()->unique()->values();

            $effectivePerPage = $allPerPage ? max($items->count(), 1) : $perPage;
            $offset = max(0, ($page - 1) * $effectivePerPage);
            $pageRows = $items->slice($offset, $effectivePerPage)->values();

            $paginator = regeneratePagination($pageRows, $items->count(), $effectivePerPage, $page);

            return Inertia::render('Backend/Reports/DoctorSummary', [
                'pageTitle' => fn () => 'Reporter Search Results',
                'companyName' => $resolveCompanyName,
                'filters' => fn () => $request->only('q', 'from', 'to', 'numOfData', 'mode'),
                // preserve the exact requested mode so the frontend can distinguish
                'mode' => fn () => $mode ?? 'technologist',
                'term' => fn () => $term,
                'meta' => fn () => ['total_reports' => $total_reports, 'distinct_cases' => $distinctCases->count()],
                'datas' => fn () => $paginator,
            ]);
        }

        // MODE: sample collector
        if ($mode === 'collector') {
            $itemsQuery = DB::table('bill_items')
                ->leftJoin('admins', 'bill_items.sample_collected_by', '=', 'admins.id')
                ->leftJoin('billings', 'bill_items.billing_id', '=', 'billings.id')
                ->leftJoin('patients', 'billings.patient_id', '=', 'patients.id');
            $applyActiveBillItems($itemsQuery);

            // Safely resolve collector/admin IDs matching the search term
            $adminHasName = Schema::hasColumn('admins', 'name');
            $adminHasFirst = Schema::hasColumn('admins', 'first_name');
            $adminHasLast = Schema::hasColumn('admins', 'last_name');

            if ($term) {
                $matchedAdminIds = [];
                try {
                    // Prefer searching by first_name/last_name concatenation to avoid
                    // relying on a non-existent `admins.name` column in some schemas.
                    if ($adminHasFirst || $adminHasLast) {
                        $parts = [];
                        if ($adminHasFirst) $parts[] = 'admins.first_name';
                        if ($adminHasLast) $parts[] = 'admins.last_name';
                        $concat = "CONCAT_WS(' ', " . implode(', ', $parts) . ")";
                        $matchedAdminIds = DB::table('admins')->whereRaw("{$concat} like ?", ["%{$term}%"])->pluck('id')->all();
                    } elseif ($adminHasName) {
                        $matchedAdminIds = DB::table('admins')->where('name', 'like', '%' . $term . '%')->pluck('id')->all();
                    }
                } catch (\Throwable $e) {
                    $matchedAdminIds = [];
                }

                // Allow searching by collector name OR test name / case number / patient name.
                $itemsQuery->where(function ($q) use ($term, $matchedAdminIds) {
                    if (!empty($matchedAdminIds)) {
                        $q->whereIn('bill_items.sample_collected_by', $matchedAdminIds);
                    }

                    $q->orWhere('bill_items.item_name', 'like', '%' . $term . '%')
                      ->orWhere('billings.case_number', 'like', '%' . $term . '%');

                    if (Schema::hasColumn('billings', 'patient_name')) {
                        $q->orWhere('billings.patient_name', 'like', '%' . $term . '%');
                    } else {
                        if (Schema::hasColumn('patients', 'name')) {
                            $q->orWhere('patients.name', 'like', '%' . $term . '%');
                        } else {
                            $parts = [];
                            if (Schema::hasColumn('patients', 'first_name')) $parts[] = 'patients.first_name';
                            if (Schema::hasColumn('patients', 'last_name')) $parts[] = 'patients.last_name';
                            if (!empty($parts)) {
                                $concat = "CONCAT_WS(' ', " . implode(', ', $parts) . ")";
                                $q->orWhereRaw("{$concat} like ?", ["%{$term}%"]);
                            }
                        }
                    }
                });
            }
            $itemsQuery->when(true, function ($q) use ($from, $to) {
                    if ($from && $to) {
                        $q->whereDate('bill_items.sample_collected_at', '>=', $from)
                          ->whereDate('bill_items.sample_collected_at', '<=', $to);
                    } elseif ($from) {
                        $q->whereDate('bill_items.sample_collected_at', '>=', $from);
                    } elseif ($to) {
                        $q->whereDate('bill_items.sample_collected_at', '<=', $to);
                    } else {
                        $q->whereDate('bill_items.sample_collected_at', now()->toDateString());
                    }
                })
                ->whereNotNull('bill_items.sample_collected_at');
            // Select collector id and compute collector_name in PHP
            $selects = ['bill_items.id', 'bill_items.item_name', 'bill_items.sample_collected_at', 'billings.id as billing_id', 'billings.case_number', 'billings.created_at as billing_date', 'bill_items.sample_collected_by as collector_id'];

            if ($hasBillNumberColumn) {
                $selects[] = 'billings.bill_number';
            } else {
                $selects[] = DB::raw("COALESCE(NULLIF(billings.case_number, ''), '') as bill_number");
            }

            // patient name fallback
            if (Schema::hasColumn('billings', 'patient_name')) {
                $selects[] = 'billings.patient_name';
            } else {
                if (Schema::hasColumn('patients', 'name')) {
                    $selects[] = DB::raw("COALESCE(NULLIF(patients.name, ''), '') as patient_name");
                } elseif (Schema::hasColumn('patients', 'first_name') || Schema::hasColumn('patients', 'last_name')) {
                    $first = Schema::hasColumn('patients', 'first_name') ? 'patients.first_name' : "''";
                    $last = Schema::hasColumn('patients', 'last_name') ? 'patients.last_name' : "''";
                    $selects[] = DB::raw("COALESCE(NULLIF(CONCAT_WS(' ', $first, $last), ''), '') as patient_name");
                } else {
                    $selects[] = DB::raw("'' as patient_name");
                }
            }

            // patient mobile fallback
            if (Schema::hasColumn('billings', 'patient_mobile')) {
                $selects[] = 'billings.patient_mobile';
            } else {
                if (Schema::hasColumn('patients', 'mobile')) {
                    $selects[] = DB::raw("COALESCE(NULLIF(patients.mobile, ''), '') as patient_mobile");
                } elseif (Schema::hasColumn('patients', 'phone')) {
                    $selects[] = DB::raw("COALESCE(NULLIF(patients.phone, ''), '') as patient_mobile");
                } else {
                    $selects[] = DB::raw("'' as patient_mobile");
                }
            }

            // price selection
            if (Schema::hasColumn('bill_items', 'unit_price')) {
                $selects[] = 'bill_items.unit_price as price';
            } elseif (Schema::hasColumn('bill_items', 'total_amount')) {
                $selects[] = 'bill_items.total_amount as price';
            } else {
                $selects[] = DB::raw("0 as price");
            }

            $items = $itemsQuery->select($selects)->orderByDesc('bill_items.sample_collected_at')->get();

            // Resolve collector display names in PHP similar to reporter handling
            $collectorIds = $items->pluck('collector_id')->filter()->unique()->values()->all();
            $collectorMap = [];
            if (!empty($collectorIds)) {
                try {
                    if ($adminHasFirst || $adminHasLast) {
                        $cols = [];
                        if ($adminHasFirst) $cols[] = 'first_name';
                        if ($adminHasLast) $cols[] = 'last_name';
                        if ($adminHasName) $cols[] = 'name';
                        $cols[] = 'id';
                        $rows = DB::table('admins')->whereIn('id', $collectorIds)->get($cols);
                        foreach ($rows as $r) {
                            $display = $resolveAdminDisplayName($r, $adminHasFirst, $adminHasLast, $adminHasName);
                            if ($display !== '') {
                                $collectorMap[$r->id] = $display;
                            }
                        }
                    } elseif ($adminHasName) {
                        $rows = DB::table('admins')->whereIn('id', $collectorIds)->get(['id', 'name']);
                        foreach ($rows as $r) {
                            $collectorMap[$r->id] = $r->name ?? '';
                        }
                    }
                } catch (\Throwable $e) {
                    $collectorMap = [];
                }
            }

            // Debug: log collectorIds and collectorMap to help troubleshooting blank names
            try {
                @file_put_contents($debugLog, date('Y-m-d H:i:s') . " - collectorIds: " . json_encode($collectorIds) . PHP_EOL, FILE_APPEND);
                @file_put_contents($debugLog, date('Y-m-d H:i:s') . " - collectorMap: " . json_encode($collectorMap) . PHP_EOL, FILE_APPEND);
                @file_put_contents($debugLog, date('Y-m-d H:i:s') . " - sample collector items (first 5): " . json_encode($items->take(5)) . PHP_EOL, FILE_APPEND);
            } catch (\Throwable $e) {
                // ignore logging failures
            }

            $items = $items->map(function ($it) use ($collectorMap) {
                $it->collector_name = $collectorMap[$it->collector_id] ?? '';
                return $it;
            });

            $total_collected = $items->count();
            $distinctCases = $items->pluck('case_number')->filter()->unique()->values();

            $effectivePerPage = $allPerPage ? max($items->count(), 1) : $perPage;
            $offset = max(0, ($page - 1) * $effectivePerPage);
            $pageRows = $items->slice($offset, $effectivePerPage)->values();

            $paginator = regeneratePagination($pageRows, $items->count(), $effectivePerPage, $page);

            return Inertia::render('Backend/Reports/DoctorSummary', [
                'pageTitle' => fn () => 'Sample Collector Results',
                'companyName' => $resolveCompanyName,
                'filters' => fn () => $request->only('q', 'from', 'to', 'numOfData', 'mode'),
                'mode' => fn () => 'collector',
                'term' => fn () => $term,
                'meta' => fn () => ['total_collected' => $total_collected, 'distinct_cases' => $distinctCases->count()],
                'datas' => fn () => $paginator,
            ]);
        }

        $hasDoctorSearchCriteria = is_string($term) && trim($term) !== '';

        if (($mode === null || $mode === '' || $mode === 'doctor' || $mode === 'doctor_daily') && $hasDoctorSearchCriteria) {
            $searchTerm = trim((string) ($term ?? ''));
            $namePattern = '%' . $searchTerm . '%';
            $hasReferralPeople = Schema::hasTable('referralpeople') && Schema::hasColumn('billings', 'referrer_id');
            $referralName = $hasReferralPeople ? 'referralpeople.name' : "''";
            $doctorNameExpression = "CASE WHEN billings.doctor_name LIKE ? THEN billings.doctor_name ELSE {$referralName} END";
            $dailyQuery = DB::table('billings')
                ->leftJoin('bill_items', 'billings.id', '=', 'bill_items.billing_id');

            if ($hasReferralPeople) {
                $dailyQuery->leftJoin('referralpeople', 'billings.referrer_id', '=', 'referralpeople.id');
            }

            $applyActiveBillItems($dailyQuery);
            $applyCategoryFilter($dailyQuery);
            $dailyQuery
                ->where('billings.case_number', 'not like', 'IPD-%')
                ->where(function ($query) use ($namePattern, $hasReferralPeople) {
                    $query->where('billings.doctor_name', 'like', $namePattern);
                    if ($hasReferralPeople) {
                        $query->orWhere('referralpeople.name', 'like', $namePattern);
                    }
                });
            $applyDateRange($dailyQuery, 'billings.created_at');

            if (Schema::hasColumn('billings', 'deleted_at')) {
                $dailyQuery->whereNull('billings.deleted_at');
            }
            if (Schema::hasColumn('billings', 'status')) {
                $dailyQuery->where('billings.status', 'Active');
            }

            $nonTestCategories = [
                'ambulance',
                'bloodbank',
                'disposable',
                'ipd',
                'medicine',
                'opd',
                'pharmacy',
                'service',
                'storeinventory',
            ];
            $normalizedCategory = "LOWER(REPLACE(REPLACE(REPLACE(bill_items.category, ' ', ''), '-', ''), '_', ''))";
            $dailyQuery->whereNotNull('bill_items.category')
                ->whereNotIn(DB::raw($normalizedCategory), $nonTestCategories);

            $patientCountExpression = Schema::hasColumn('billings', 'patient_id')
                ? 'COUNT(DISTINCT billings.patient_id) + COUNT(DISTINCT CASE WHEN billings.patient_id IS NULL THEN billings.case_number END)'
                : 'COUNT(DISTINCT billings.case_number)';
            $quantityExpression = Schema::hasColumn('bill_items', 'quantity')
                ? 'COALESCE(bill_items.quantity, 1)'
                : '1';
            $amountExpression = Schema::hasColumn('bill_items', 'total_amount')
                ? 'COALESCE(bill_items.total_amount, 0)'
                : (Schema::hasColumn('bill_items', 'unit_price')
                    ? "COALESCE(bill_items.unit_price, 0) * {$quantityExpression}"
                    : '0');

            $dailyRows = $dailyQuery
                ->selectRaw('DATE(billings.created_at) as billing_date')
                ->selectRaw("{$doctorNameExpression} as doctor_name", [$namePattern])
                ->selectRaw("{$patientCountExpression} as patient_count")
                ->selectRaw("SUM({$quantityExpression}) as test_count")
                ->selectRaw("SUM({$amountExpression}) as total_amount")
                ->groupByRaw("DATE(billings.created_at), {$doctorNameExpression}", [$namePattern])
                ->orderByDesc('billing_date')
                ->orderBy('doctor_name')
                ->get();

            $totalPatients = (int) $dailyRows->sum('patient_count');
            $totalTests = (float) $dailyRows->sum('test_count');
            $grandTotal = (float) $dailyRows->sum('total_amount');
            $effectivePerPage = $allPerPage ? max($dailyRows->count(), 1) : $perPage;
            $offset = max(0, ($page - 1) * $effectivePerPage);
            $pageRows = $dailyRows->slice($offset, $effectivePerPage)->values();
            $paginator = regeneratePagination($pageRows, $dailyRows->count(), $effectivePerPage, $page);

            return Inertia::render('Backend/Reports/DoctorSummary', [
                'pageTitle' => fn () => 'Doctor-wise Daily Summary',
                'companyName' => $resolveCompanyName,
                'filters' => fn () => $request->only('q', 'from', 'to', 'numOfData', 'mode'),
                'mode' => fn () => 'doctor_daily',
                'term' => fn () => $searchTerm,
                'meta' => fn () => [
                    'total_cases' => $totalPatients,
                    'total_items' => $totalTests,
                    'grand_total' => round($grandTotal, 2),
                ],
                'datas' => fn () => $paginator,
            ]);
        }

        // Default: original doctor summary aggregated by doctor
        $base = DB::table('billings')
            ->leftJoin('bill_items', function ($join) use ($applyActiveBillItems) {
                $join->on('billings.id', '=', 'bill_items.billing_id');
                $applyActiveBillItems($join);
            })
            ->whereNotNull('billings.doctor_id')
            ->where('billings.case_number', 'not like', 'IPD-%');
        $applyCategoryFilter($base);

        if ($from && $to) {
            $base->whereDate('billings.created_at', '>=', $from)
                 ->whereDate('billings.created_at', '<=', $to);
        } elseif ($from) {
            $base->whereDate('billings.created_at', '>=', $from);
        } elseif ($to) {
            $base->whereDate('billings.created_at', '<=', $to);
        }

        if ($term) {
            $base->where('billings.doctor_name', 'like', '%' . $term . '%');
        }

        $rows = $base
            ->select(
                'billings.doctor_id',
                DB::raw('MAX(billings.doctor_name) as doctor_name'),
                DB::raw('COUNT(DISTINCT billings.case_number) as case_count'),
            DB::raw($subcat
                ? 'COUNT(bill_items.id) as pathology_count'
                : 'SUM(CASE WHEN bill_items.category = "Pathology" THEN 1 ELSE 0 END) as pathology_count')
        )
            ->groupBy('billings.doctor_id')
            ->orderByDesc('case_count')
            ->get();

        $doctorIds = $rows->pluck('doctor_id')->filter()->unique()->values()->all();

        $tests = collect();
        if (!empty($doctorIds)) {
            $testsQuery = DB::table('bill_items')
                ->join('billings', 'bill_items.billing_id', '=', 'billings.id')
                ->whereIn('billings.doctor_id', $doctorIds)
                ->where('billings.case_number', 'not like', 'IPD-%')
                ->when($from && $to, function ($query) use ($from, $to) {
                    $query->whereDate('billings.created_at', '>=', $from)
                          ->whereDate('billings.created_at', '<=', $to);
                })
                ->when($term, function ($query) use ($term) {
                    $query->where('billings.doctor_name', 'like', '%' . $term . '%');
                });
            $applyActiveBillItems($testsQuery);
            $applyCategoryFilter($testsQuery);
            if (!$subcat) {
                $testsQuery->where('bill_items.category', 'Pathology');
            }
            $tests = $testsQuery
                ->select('billings.doctor_id', 'bill_items.item_name', DB::raw('COUNT(*) as cnt'))
                ->groupBy('billings.doctor_id', 'bill_items.item_name')
                ->orderByDesc('cnt')
                ->get();
        }

        $testsByDoctor = [];
        foreach ($tests as $t) {
            $testsByDoctor[$t->doctor_id][] = ['item_name' => $t->item_name, 'count' => (int) $t->cnt];
        }

        $effectivePerPage = $allPerPage ? max($rows->count(), 1) : $perPage;
        $offset = max(0, ($page - 1) * $effectivePerPage);

        $pageRows = $rows->slice($offset, $effectivePerPage)->values()->map(function ($r) use ($testsByDoctor) {
            return (object) [
                'doctor_id' => $r->doctor_id,
                'doctor_name' => $r->doctor_name,
                'case_count' => (int) $r->case_count,
                'pathology_count' => (int) $r->pathology_count,
                'top_tests' => $testsByDoctor[$r->doctor_id] ?? [],
            ];
        });

        $paginator = regeneratePagination($pageRows, $rows->count(), $effectivePerPage, $page);

        return Inertia::render('Backend/Reports/DoctorSummary', [
            'pageTitle' => fn () => 'Report Summary',
            'companyName' => $resolveCompanyName,
            'filters' => fn () => $request->only('q', 'from', 'to', 'numOfData', 'mode', 'subcat'),
            'mode' => fn () => 'doctor',
            'term' => fn () => $term,
            'datas' => fn () => $paginator,
        ]);
        } catch (\Throwable $e) {
            @file_put_contents($debugLog, date('Y-m-d H:i:s') . " - EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . PHP_EOL, FILE_APPEND);
            Log::error('DoctorSummaryController@index error: ' . $e->getMessage(), ['exception' => $e]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['message' => 'Server error', 'error' => $e->getMessage()], 500);
            }

            abort(500, 'Server error');
        }
    }
}
