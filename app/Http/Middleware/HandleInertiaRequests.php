<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\AdminDetail;
use App\Models\CashCounterSession;
use App\Models\Menu;
use App\Models\MedicineInventory;
use App\Services\CashCounterService;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use Closure;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {

        $sideMenus = [];
        $companyInfo = [];
        $adminPermissions = collect();
        $strictSidebarFiltering = false;
        $dashboardNetIncome = 0.0;

        // Load cached web setting early so we can prefer it for company info
        // when session fallback is not present.
        $webSetting = get_cached_web_setting();

        // In local / debug environments, force a fresh read to avoid stale
        // cached values during active development. This prevents a saved
        // WebSetting from reverting on a full page reload while developing.
        if (app()->environment('local') && config('app.debug')) {
            try {
                get_cached_web_setting(true);
                $webSetting = get_cached_web_setting();
            } catch (\Throwable $_) {
                // ignore cache refresh failures in dev
            }
        }

        // Try to detect the admin user from the request (explicit guard) first,
        // fall back to the default auth guard accessor. This ensures that the
        // side menus are available even when route-level middleware hasn't yet
        // called Auth::shouldUse('admin').
        $adminUser = $request->user('admin') ?? auth()->guard('admin')->user();

        if ($adminUser && strcasecmp(trim((string) ($adminUser->status ?? '')), 'Active') === 0) {
            // Force a fresh role/permission snapshot so recent role changes reflect instantly.
            try {
                $adminUser = Admin::query()
                    ->with(['roles.permissions', 'permissions'])
                    ->find($adminUser->id) ?? $adminUser;
            } catch (\Throwable $exception) {
                // Fall back to currently authenticated user instance.
            }

            try {
                $adminPermissions = $adminUser->getAllPermissions()
                    ->pluck('name')
                    ->filter()
                    ->map(function ($name) {
                        return strtolower(trim((string) $name));
                    })->unique()->values();
            } catch (\Throwable $exception) {
                $adminPermissions = collect();
            }

            try {
                $strictEmailsEnv = env('SIDEBAR_STRICT_EMAILS', '');
                $strictEmails = array_filter(array_map('trim', explode(',', (string) $strictEmailsEnv)));
                $adminEmailLower = strtolower(trim((string) ($adminUser->email ?? '')));
                $strictSidebarFiltering = $adminEmailLower !== ''
                    && in_array($adminEmailLower, array_map('strtolower', $strictEmails), true);
            } catch (\Throwable $exception) {
                $strictSidebarFiltering = false;
            }

            $moduleSlugs = collect();
            try {
                if (method_exists($adminUser, 'modules')) {
                    $moduleSlugs = $adminUser->modules()
                        ->pluck('slug')
                        ->map(function ($slug) {
                            return strtolower(trim((string) $slug));
                        })->filter()->sort()->values();
                }
            } catch (\Throwable $exception) {
                $moduleSlugs = collect();
            }

            $menuSchemaVersion = $this->safeCacheRemember('sidebar.menu_schema_version', 300, function () {
                try {
                    $menuStats = Menu::query()
                        ->selectRaw('MAX(updated_at) as updated_at, MAX(id) as max_id, COUNT(*) as count')
                        ->first();

                    $updatedAt = trim((string) data_get($menuStats, 'updated_at', ''));
                    $maxId = trim((string) data_get($menuStats, 'max_id', ''));
                    $count = trim((string) data_get($menuStats, 'count', ''));

                    return sha1($updatedAt . '|' . $maxId . '|' . $count . '|' . now()->timestamp);
                } catch (\Throwable $exception) {
                    return (string) now()->timestamp;
                }
            }) ?? (string) now()->timestamp;

            $sideMenuCacheKey = sprintf(
                'sidebar.menus.u%s.p%s.m%s.s%s.v%s',
                (string) $adminUser->id,
                substr(sha1($adminPermissions->sort()->values()->implode('|')), 0, 16),
                substr(sha1($moduleSlugs->implode('|')), 0, 16),
                $strictSidebarFiltering ? '1' : '0',
                (string) $menuSchemaVersion
            );

            $sideMenus = $this->rememberSidebarMenus($sideMenuCacheKey, $adminUser);
        }

        $sideMenus = collect($sideMenus)
            ->map(function ($menu) {
                $children = collect(data_get($menu, 'childrens', []))
                    ->unique(function ($child) {
                        $route = trim((string) data_get($child, 'route', ''));
                        return $route !== ''
                            ? ('route:' . $route)
                            : ('name:' . trim((string) data_get($child, 'name', '')));
                    })
                    ->values();

                if ($menu instanceof Collection) {
                    $menu = $menu->toArray();
                }

                if (is_array($menu)) {
                    $menu['childrens'] = $children;
                    return $menu;
                }

                $menu->childrens = $children;
                return $menu;
            })
            ->unique(function ($menu) {
                $route = trim((string) data_get($menu, 'route', ''));
                return $route !== ''
                    ? ('route:' . $route)
                    : ('name:' . trim((string) data_get($menu, 'name', '')));
            })
            ->values();

        // Prefer the active `WebSetting` so updates in WebSetting take
        // precedence and propagate immediately to Inertia shared props.
        // Fall back to an explicit session override when present, then
        // to the legacy `Company::first()`.
        if (!empty($webSetting)) {
            $companyInfo = [
                'id' => $webSetting->id ?? null,
                'name' => $webSetting->company_name ?? null,
                'short_name' => $webSetting->company_short_name ?? null,
                'phone' => $webSetting->phone ?? null,
                'email' => $webSetting->email ?? null,
                'logo' => $webSetting->logo ?? null,
                'favicon' => $webSetting->icon ?? null,
                'address' => $webSetting->address ?? null,
                'sorting' => 0,
                'status' => $webSetting->status ?? 'Active',
                'created_at' => $webSetting->created_at ?? null,
                'updated_at' => $webSetting->updated_at ?? null,
            ];
        } elseif (session()->has('companyInfo')) {
            $companyInfo = session()->get('companyInfo');
        } else {
            try {
                $companyInfo = app('db')->connection()->getSchemaBuilder()->hasTable('companies')
                    ? Company::first()
                    : [];
            } catch (\Throwable $exception) {
                $companyInfo = [];
            }
        }

        $medicineExpiryAlert = [
            'expired_count' => 0,
            'expiring_soon_count' => 0,
            'days_window' => 30,
        ];

        $activityLogAlert = [
            'can_view' => false,
            'today_count' => 0,
            'recent' => [],
        ];

        $authInfo = null;
        $cashCounterInfo = null;

        if ($adminUser) {
            $today = Carbon::today()->toDateString();
            $soonDate = Carbon::today()->addDays(30)->toDateString();

            $medicineExpiryAlert = $this->safeCacheRemember(
                sprintf('alerts.medicine_expiry.%s.%s', $today, $soonDate),
                max((int) config('cache.medicine_expiry_alert_ttl_seconds', 120), 30),
                function () use ($today, $soonDate) {
                    return [
                        'expired_count' => MedicineInventory::query()
                            ->where('status', 'Active')
                            ->whereNotNull('expiry_date')
                            ->whereDate('expiry_date', '<', $today)
                            ->count(),
                        'expiring_soon_count' => MedicineInventory::query()
                            ->where('status', 'Active')
                            ->whereNotNull('expiry_date')
                            ->whereDate('expiry_date', '>=', $today)
                            ->whereDate('expiry_date', '<=', $soonDate)
                            ->count(),
                        'days_window' => 30,
                    ];
                }
            ) ?? [
                'expired_count' => 0,
                'expiring_soon_count' => 0,
                'days_window' => 30,
            ];

            try {
                $authInfo = AdminDetail::with(['admin', 'department', 'designation'])
                    ->where('admin_id', $adminUser->id)
                    ->first();
            } catch (\Throwable $_) {
                $authInfo = null;
            }

            try {
                $detail = $adminUser?->details;
                $filterType = $detail?->dashboard_filter_type ?? 'daily';
                $filterFrom = $detail?->dashboard_filter_from;
                $filterTo = $detail?->dashboard_filter_to;

                /** @var DashboardService $dashboardService */
                $dashboardService = app(DashboardService::class);
                $filter = $dashboardService->resolveDashboardFilter($filterType, $filterFrom, $filterTo);
                $dashboardNetIncome = (float) $dashboardService->countNetIncome($filter['dbRange'], $filter['appRange']);
            } catch (\Throwable $_) {
                $dashboardNetIncome = 0.0;
            }

            try {
                $adminName = trim((string) ($adminUser->name ?? ''));
                $activeCashCounterSession = CashCounterSession::query()
                    ->whereRaw('LOWER(status) = ?', ['open'])
                    ->where(function ($query) use ($adminUser, $adminName) {
                        $query->where('created_by', $adminUser->id);

                        if ($adminName !== '') {
                            $query->orWhere(function ($subQuery) use ($adminName) {
                                $subQuery->whereNull('created_by')
                                    ->where('user_name', $adminName);
                            });
                        }
                    })
                    ->latest('opened_at')
                    ->first();

                if ($activeCashCounterSession) {
                    $cashCounterInfo = (new CashCounterService())->getSummary($activeCashCounterSession->id);
                }
            } catch (\Throwable $_) {
                $cashCounterInfo = null;
            }

            $activityLogAlert['can_view'] = $adminPermissions->contains('activity-log-view')
                || (bool) ($adminUser?->can('activity-log-view'));

            if ($activityLogAlert['can_view']) {
                $activityData = [
                    'today_count' => ActivityLog::query()
                        ->whereDate('created_at', $today)
                        ->count(),
                    'recent' => ActivityLog::query()
                        ->orderByDesc('created_at')
                        ->limit(8)
                        ->get(['id', 'user_name', 'module', 'action', 'description', 'status', 'created_at'])
                        ->map(function ($log) {
                            return [
                                'id' => $log->id,
                                'user_name' => $log->user_name,
                                'module' => $log->module,
                                'action' => $log->action,
                                'description' => $log->description,
                                'status' => $log->status,
                                'created_at' => optional($log->created_at)->format('d-m-Y h:i A'),
                            ];
                        })
                        ->values()
                        ->toArray(),
                ];

                $activityLogAlert['today_count'] = (int) ($activityData['today_count'] ?? 0);
                $activityLogAlert['recent'] = (array) ($activityData['recent'] ?? []);
            }
        }


        // Prepare a sanitized copy of web setting for frontend shared props.
        $sharedWebSetting = null;
        if (!empty($webSetting)) {
            try {
                $sharedWebSetting = $webSetting->toArray();

                // Ensure file paths from shared props are resolved using the
                // model accessors so frontend UI receives absolute URLs.
                $sharedWebSetting['logo'] = $webSetting->logo;
                $sharedWebSetting['icon'] = $webSetting->icon;
                $sharedWebSetting['mobile_app_logo'] = $webSetting->mobile_app_logo;

                // Remove reporting page top-margin from shared props so
                // changing it in Report Settings does not affect non-report UI pages.
                if (is_array($sharedWebSetting) && array_key_exists('attendance_device_options', $sharedWebSetting)) {
                    $ado = $sharedWebSetting['attendance_device_options'];
                    if (is_string($ado) && trim($ado) !== '') {
                        $ado = json_decode($ado, true) ?? $ado;
                    }
                    if (is_array($ado) && array_key_exists('reporting', $ado)) {
                        if (is_array($ado['reporting']) && array_key_exists('layout', $ado['reporting'])) {
                            unset($ado['reporting']['layout']['page_margin_top']);
                        }
                        $sharedWebSetting['attendance_device_options'] = $ado;
                    }
                }
            } catch (\Throwable $_) {
                $sharedWebSetting = $webSetting;
            }
        }

        return array_merge(parent::share($request), [
            'ziggy' => function () use ($request) {
                return array_merge((new Ziggy)->toArray(), [
                    'location' => $request->url(),
                ]);
            },
            'flash' => [
                'successMessage' => $request->session()->get('successMessage'),
                'errorMessage' => $request->session()->get('errorMessage'),
                'billId' => $request->session()->get('billId'),
                'savedPassword' => $request->session()->get('savedPassword'),
                'cashCounterPrintUrl' => $request->session()->get('cashCounterPrintUrl'),
            ],
            'auth' => [
                'admin' => fn () => auth('admin')->user(),
                'permissions' => $adminPermissions,
                'sideMenus' => $sideMenus,
                'strictSidebarFiltering' => $strictSidebarFiltering,
            ],

            'companyInfo' => $companyInfo,
            'appMeta' => [
                'version' => (string) config('app.version', env('APP_VERSION', '2.1.1')),
            ],
            'locale' => app()->getLocale(),
            // Provide both `webSetting` and lowercase `websetting` keys so Inertia
            // partial reloads requesting `only: ['websetting']` will receive
            // the updated settings regardless of casing used by the client.
            'webSetting' => $sharedWebSetting ?? $webSetting,
            'websetting' => $sharedWebSetting ?? $webSetting,
            'pharmacyAlerts' => [
                'medicineExpiry' => $medicineExpiryAlert,
            ],
            'activityLogAlerts' => $activityLogAlert,
            'authInfo' => fn () => $authInfo,
            'cashCounterInfo' => fn () => $cashCounterInfo,
            'dashboardNetIncome' => fn () => (float) $dashboardNetIncome,
            'loginTexts' => [
                'banner' => env('LOGIN_BANNER', 'Hospital Management Suite'),
                'title' => env('LOGIN_TITLE', 'Welcome Back.'),
                'subtitle' => env('LOGIN_SUBTITLE', 'Continue with your secure account and manage all operations with confidence.'),
            ],
        ]);
    }

    protected function rememberSidebarMenus(string $cacheKey, $adminUser)
    {
        $ttl = max((int) config('cache.side_menu_ttl_seconds', 180), 30);

        return $this->safeCacheRemember($cacheKey, $ttl, function () use ($adminUser) {
            return getSideMenus($adminUser);
        }) ?? [];
    }

    protected function safeCacheRemember(string $cacheKey, int $ttl, $callback)
    {
        try {
            return Cache::remember($cacheKey, $ttl, $callback);
        } catch (\Throwable $exception) {
            try {
                return $callback();
            } catch (\Throwable $fallbackException) {
                return null;
            }
        }
    }
}
