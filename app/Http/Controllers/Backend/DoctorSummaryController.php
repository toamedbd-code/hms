<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class DoctorSummaryController extends Controller
{
    public function index(Request $request)
    {
        $debugLog = storage_path('logs/doctor-summary-debug.log');
        try {
            $mode = $request->query('mode'); // test|referrer|technologist|pathologist|collector or null
        $term = $request->query('q') ?? $request->query('term') ?? null;
        $from = $request->query('from');
        $to = $request->query('to');
        $perPage = (int) ($request->query('numOfData') ?? $request->query('per_page') ?? 10);
        $page = (int) ($request->query('page') ?? 1);

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

        // MODE: search by test name (list case ids where a test exists + totals)
        if ($mode === 'test') {
            $itemsQuery = DB::table('bill_items')
                ->join('billings', 'bill_items.billing_id', '=', 'billings.id')
                ->leftJoin('patients', 'billings.patient_id', '=', 'patients.id')
                ->where('bill_items.category', 'Pathology')
                ->when($term, function ($q) use ($term) {
                    $q->where('bill_items.item_name', 'like', '%' . $term . '%');
                })
                ->when(true, function ($q) use ($applyDateRange) {
                    $applyDateRange($q, 'billings.created_at');
                });

            $selects = ['billings.id', 'billings.case_number', 'bill_items.item_name', 'bill_items.id as bill_item_id'];

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

            // price selection (unit_price preferred, fallback to total_amount)
            if (Schema::hasColumn('bill_items', 'unit_price')) {
                $selects[] = 'bill_items.unit_price as price';
            } elseif (Schema::hasColumn('bill_items', 'total_amount')) {
                $selects[] = 'bill_items.total_amount as price';
            } else {
                $selects[] = DB::raw("0 as price");
            }

            $itemsQuery = $itemsQuery->select($selects)->orderByDesc('billings.created_at');


            $items = $itemsQuery->get();

            $distinctBills = $items->map(function ($i) {
                return (object) [
                    'id' => $i->id,
                    'case_number' => $i->case_number,
                    'patient_name' => $i->patient_name,
                    'patient_mobile' => $i->patient_mobile,
                ];
            })->unique('id')->values();

            $total_cases = $distinctBills->count();
            // total_items represents matched item count (quantity)
            $total_items = $items->count();

            // Grand total of matched item prices
            $grand_total = $items->reduce(function ($carry, $it) {
                return $carry + (float) ($it->price ?? 0);
            }, 0);

            $offset = max(0, ($page - 1) * $perPage);
            $pageRows = $distinctBills->slice($offset, $perPage)->values()->map(function ($r) use ($items) {
                $matchedItems = $items->filter(function ($it) use ($r) {
                    return $it->id == $r->id;
                });

                $matched = $matchedItems->pluck('item_name')->unique()->values();
                $qty = $matchedItems->count();
                $sumPrice = $matchedItems->reduce(function ($carry, $it) {
                    return $carry + (float) ($it->price ?? 0);
                }, 0);

                return (object) [
                    'id' => $r->id,
                    'case_number' => $r->case_number,
                    'patient_name' => $r->patient_name,
                    'patient_mobile' => $r->patient_mobile,
                    'matched_tests' => $matched,
                    'quantity' => $qty,
                    'price' => $sumPrice,
                ];
            });

            $paginator = regeneratePagination($pageRows, $distinctBills->count(), $perPage, $page);

            return Inertia::render('Backend/Reports/DoctorSummary', [
                'pageTitle' => fn () => 'Test Search Results',
                'filters' => fn () => $request->only('q', 'from', 'to', 'numOfData', 'mode'),
                'mode' => fn () => 'test',
                'term' => fn () => $term,
                'meta' => fn () => ['total_cases' => $total_cases, 'total_items' => $total_items, 'grand_total' => $grand_total],
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
            $referrerSelects = ['billings.id', 'billings.case_number', 'billings.referrer_id'];
            $adminHasName = Schema::hasColumn('admins', 'name');
            $adminHasFirst = Schema::hasColumn('admins', 'first_name');
            $adminHasLast = Schema::hasColumn('admins', 'last_name');

            if ($term) {
                $matchedAdminIds = [];
                try {
                    if ($adminHasName) {
                        $matchedAdminIds = DB::table('admins')->where('name', 'like', '%' . $term . '%')->pluck('id')->all();
                    } elseif ($adminHasFirst || $adminHasLast) {
                        $parts = [];
                        if ($adminHasFirst) $parts[] = 'first_name';
                        if ($adminHasLast) $parts[] = 'last_name';
                        $concat = "CONCAT_WS(' ', " . implode(', ', $parts) . ")";
                        $matchedAdminIds = DB::table('admins')->whereRaw("{$concat} like ?", ["%{$term}%"])->pluck('id')->all();
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
                    if ($adminHasName) {
                        $rows = DB::table('admins')->whereIn('id', $referrerIds)->get(['id', 'name']);
                        foreach ($rows as $r) {
                            $refMap[$r->id] = $r->name ?? '';
                        }
                    } else {
                        $cols = [];
                        if ($adminHasFirst) $cols[] = 'first_name';
                        if ($adminHasLast) $cols[] = 'last_name';
                        $cols[] = 'id';
                        $rows = DB::table('admins')->whereIn('id', $referrerIds)->get($cols);
                        foreach ($rows as $r) {
                            $fn = $adminHasFirst ? ($r->first_name ?? '') : '';
                            $ln = $adminHasLast ? ($r->last_name ?? '') : '';
                            $refMap[$r->id] = trim($fn . ' ' . $ln);
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

            $offset = max(0, ($page - 1) * $perPage);
            $pageRows = $distinct->slice($offset, $perPage)->values();

            $paginator = regeneratePagination($pageRows, $distinct->count(), $perPage, $page);

            return Inertia::render('Backend/Reports/DoctorSummary', [
                'pageTitle' => fn () => 'Referrer Search Results',
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

            // Safely resolve admin IDs matching the search term to avoid referencing
            // potentially-missing admin name columns in SQL. This prevents SQL errors
            // when different installations have different admin column schemas.
            $adminHasName = Schema::hasColumn('admins', 'name');
            $adminHasFirst = Schema::hasColumn('admins', 'first_name');
            $adminHasLast = Schema::hasColumn('admins', 'last_name');

            if ($term) {
                $matchedAdminIds = [];
                try {
                    if ($adminHasName) {
                        $matchedAdminIds = DB::table('admins')->where('name', 'like', '%' . $term . '%')->pluck('id')->all();
                    } elseif ($adminHasFirst || $adminHasLast) {
                        $parts = [];
                        if ($adminHasFirst) $parts[] = 'first_name';
                        if ($adminHasLast) $parts[] = 'last_name';
                        $concat = "CONCAT_WS(' ', " . implode(', ', $parts) . ")";
                        $matchedAdminIds = DB::table('admins')->whereRaw("{$concat} like ?", ["%{$term}%"])->pluck('id')->all();
                    }
                } catch (\Throwable $e) {
                    $matchedAdminIds = [];
                }

                if (!empty($matchedAdminIds)) {
                    $itemsQuery->whereIn('bill_items.reported_by', $matchedAdminIds);
                } else {
                    // No matching admins found — ensure no results rather than risking
                    // constructing SQL with unknown columns.
                    $itemsQuery->whereRaw('0 = 1');
                }
            }

            $itemsQuery->when(true, function ($q) use ($from, $to) {
                    if ($from && $to) {
                        $q->whereDate('bill_items.reported_at', '>=', $from)
                          ->whereDate('bill_items.reported_at', '<=', $to);
                    } elseif ($from) {
                        $q->whereDate('bill_items.reported_at', '>=', $from);
                    } elseif ($to) {
                        $q->whereDate('bill_items.reported_at', '<=', $to);
                    } else {
                        $q->whereDate('bill_items.reported_at', now()->toDateString());
                    }
                })
                ->whereNotNull('bill_items.reported_at');

            // Always select the reporter id (from bill_items) and compute display name in PHP
            // to avoid SQL errors due to missing admin name columns.
            $selects = ['bill_items.id', 'bill_items.item_name', 'bill_items.reported_at', 'billings.case_number', 'bill_items.reported_by as reporter_id'];

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
                    if ($adminHasName) {
                        $rows = DB::table('admins')->whereIn('id', $reporterIds)->get(['id', 'name']);
                        foreach ($rows as $r) {
                            $adminMap[$r->id] = $r->name ?? '';
                        }
                    } else {
                        $cols = [];
                        if ($adminHasFirst) $cols[] = 'first_name';
                        if ($adminHasLast) $cols[] = 'last_name';
                        $cols[] = 'id';
                        $rows = DB::table('admins')->whereIn('id', $reporterIds)->get($cols);
                        foreach ($rows as $r) {
                            $fn = $adminHasFirst ? ($r->first_name ?? '') : '';
                            $ln = $adminHasLast ? ($r->last_name ?? '') : '';
                            $adminMap[$r->id] = trim($fn . ' ' . $ln);
                        }
                    }
                } catch (\Throwable $e) {
                    $adminMap = [];
                }
            }

            $items = $items->map(function ($it) use ($adminMap) {
                $it->reporter_name = $adminMap[$it->reporter_id] ?? '';
                return $it;
            });

            $total_reports = $items->count();
            $distinctCases = $items->pluck('case_number')->filter()->unique()->values();

            $offset = max(0, ($page - 1) * $perPage);
            $pageRows = $items->slice($offset, $perPage)->values();

            $paginator = regeneratePagination($pageRows, $items->count(), $perPage, $page);

            return Inertia::render('Backend/Reports/DoctorSummary', [
                'pageTitle' => fn () => 'Reporter Search Results',
                'filters' => fn () => $request->only('q', 'from', 'to', 'numOfData', 'mode'),
                'mode' => fn () => 'technologist',
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

            // Safely resolve collector/admin IDs matching the search term
            $adminHasName = Schema::hasColumn('admins', 'name');
            $adminHasFirst = Schema::hasColumn('admins', 'first_name');
            $adminHasLast = Schema::hasColumn('admins', 'last_name');

            if ($term) {
                $matchedAdminIds = [];
                try {
                    if ($adminHasName) {
                        $matchedAdminIds = DB::table('admins')->where('name', 'like', '%' . $term . '%')->pluck('id')->all();
                    } elseif ($adminHasFirst || $adminHasLast) {
                        $parts = [];
                        if ($adminHasFirst) $parts[] = 'first_name';
                        if ($adminHasLast) $parts[] = 'last_name';
                        $concat = "CONCAT_WS(' ', " . implode(', ', $parts) . ")";
                        $matchedAdminIds = DB::table('admins')->whereRaw("{$concat} like ?", ["%{$term}%"])->pluck('id')->all();
                    }
                } catch (\Throwable $e) {
                    $matchedAdminIds = [];
                }

                if (!empty($matchedAdminIds)) {
                    $itemsQuery->whereIn('bill_items.sample_collected_by', $matchedAdminIds);
                } else {
                    $itemsQuery->whereRaw('0 = 1');
                }
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
            $selects = ['bill_items.id', 'bill_items.item_name', 'bill_items.sample_collected_at', 'billings.case_number', 'bill_items.sample_collected_by as collector_id'];

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
                    if ($adminHasName) {
                        $rows = DB::table('admins')->whereIn('id', $collectorIds)->get(['id', 'name']);
                        foreach ($rows as $r) {
                            $collectorMap[$r->id] = $r->name ?? '';
                        }
                    } else {
                        $cols = [];
                        if ($adminHasFirst) $cols[] = 'first_name';
                        if ($adminHasLast) $cols[] = 'last_name';
                        $cols[] = 'id';
                        $rows = DB::table('admins')->whereIn('id', $collectorIds)->get($cols);
                        foreach ($rows as $r) {
                            $fn = $adminHasFirst ? ($r->first_name ?? '') : '';
                            $ln = $adminHasLast ? ($r->last_name ?? '') : '';
                            $collectorMap[$r->id] = trim($fn . ' ' . $ln);
                        }
                    }
                } catch (\Throwable $e) {
                    $collectorMap = [];
                }
            }

            $items = $items->map(function ($it) use ($collectorMap) {
                $it->collector_name = $collectorMap[$it->collector_id] ?? '';
                return $it;
            });

            $total_collected = $items->count();
            $distinctCases = $items->pluck('case_number')->filter()->unique()->values();

            $offset = max(0, ($page - 1) * $perPage);
            $pageRows = $items->slice($offset, $perPage)->values();

            $paginator = regeneratePagination($pageRows, $items->count(), $perPage, $page);

            return Inertia::render('Backend/Reports/DoctorSummary', [
                'pageTitle' => fn () => 'Sample Collector Results',
                'filters' => fn () => $request->only('q', 'from', 'to', 'numOfData', 'mode'),
                'mode' => fn () => 'collector',
                'term' => fn () => $term,
                'meta' => fn () => ['total_collected' => $total_collected, 'distinct_cases' => $distinctCases->count()],
                'datas' => fn () => $paginator,
            ]);
        }

        // Default: original doctor summary aggregated by doctor
        $base = DB::table('billings')
            ->leftJoin('bill_items', 'billings.id', '=', 'bill_items.billing_id')
            ->whereNotNull('billings.doctor_id')
            ->where('billings.case_number', 'not like', 'IPD-%');

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
                DB::raw('SUM(CASE WHEN bill_items.category = "Pathology" THEN 1 ELSE 0 END) as pathology_count')
            )
            ->groupBy('billings.doctor_id')
            ->orderByDesc('case_count')
            ->get();

        $doctorIds = $rows->pluck('doctor_id')->filter()->unique()->values()->all();

        $tests = collect();
        if (!empty($doctorIds)) {
            $tests = DB::table('bill_items')
                ->join('billings', 'bill_items.billing_id', '=', 'billings.id')
                ->whereIn('billings.doctor_id', $doctorIds)
                ->where('billings.case_number', 'not like', 'IPD-%')
                ->when($from && $to, function ($query) use ($from, $to) {
                    $query->whereDate('billings.created_at', '>=', $from)
                          ->whereDate('billings.created_at', '<=', $to);
                })
                ->when($term, function ($query) use ($term) {
                    $query->where('billings.doctor_name', 'like', '%' . $term . '%');
                })
                ->where('bill_items.category', 'Pathology')
                ->select('billings.doctor_id', 'bill_items.item_name', DB::raw('COUNT(*) as cnt'))
                ->groupBy('billings.doctor_id', 'bill_items.item_name')
                ->orderByDesc('cnt')
                ->get();
        }

        $testsByDoctor = [];
        foreach ($tests as $t) {
            $testsByDoctor[$t->doctor_id][] = ['item_name' => $t->item_name, 'count' => (int) $t->cnt];
        }

        $offset = max(0, ($page - 1) * $perPage);

        $pageRows = $rows->slice($offset, $perPage)->values()->map(function ($r) use ($testsByDoctor) {
            return (object) [
                'doctor_id' => $r->doctor_id,
                'doctor_name' => $r->doctor_name,
                'case_count' => (int) $r->case_count,
                'pathology_count' => (int) $r->pathology_count,
                'top_tests' => $testsByDoctor[$r->doctor_id] ?? [],
            ];
        });

        $paginator = regeneratePagination($pageRows, $rows->count(), $perPage, $page);

        return Inertia::render('Backend/Reports/DoctorSummary', [
            'pageTitle' => fn () => 'Report Summary',
            'filters' => fn () => $request->only('q', 'from', 'to', 'numOfData'),
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
