<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\MedicineInventory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Inertia\Middleware;
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

            // always regenerate menus from current permissions (don't cache in session)
            $sideMenus = getSideMenus($adminUser);
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
            $companyInfo = Company::first();
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

        if ($adminUser) {
            /** @var \App\Models\Admin|null $adminUser */
            $adminUser = $adminUser ?: ($request->user('admin') ?? auth()->guard('admin')->user());
            $today = Carbon::today()->toDateString();
            $soonDate = Carbon::today()->addDays(30)->toDateString();

            $medicineExpiryAlert['expired_count'] = MedicineInventory::query()
                ->where('status', 'Active')
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<', $today)
                ->count();

            $medicineExpiryAlert['expiring_soon_count'] = MedicineInventory::query()
                ->where('status', 'Active')
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', $soonDate)
                ->count();

            $activityLogAlert['can_view'] = (bool) ($adminUser?->can('activity-log-view'));

            if ($activityLogAlert['can_view']) {
                $activityLogAlert['today_count'] = ActivityLog::query()
                    ->whereDate('created_at', $today)
                    ->count();

                $activityLogAlert['recent'] = ActivityLog::query()
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
                    ->toArray();
            }
        }

        $adminPermissions = collect();
        if ($adminUser) {
            // Normalize permissions to lowercase trimmed strings for consistency
            $adminPermissions = $adminUser->getAllPermissions()
                ->pluck('name')
                ->filter()
                ->map(function ($n) {
                    return strtolower(trim((string) $n));
                })->unique()->values();
        }

        // Per-user strict sidebar filtering flag (controlled by SIDEBAR_STRICT_EMAILS)
        $strictSidebarFiltering = false;
        try {
            if ($adminUser) {
                $strictEmailsEnv = env('SIDEBAR_STRICT_EMAILS', '');
                $strictEmails = array_filter(array_map('trim', explode(',', (string) $strictEmailsEnv)));
                $adminEmailLower = strtolower(trim((string) ($adminUser->email ?? '')));
                $strictSidebarFiltering = $adminEmailLower && in_array($adminEmailLower, array_map('strtolower', $strictEmails), true);
            }
        } catch (\Throwable $_) {
            $strictSidebarFiltering = false;
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
            ],
            'auth' => [
                'admin' => fn () => auth('admin')->user(),
                'permissions' => $adminPermissions,
                'sideMenus' => $sideMenus,
                'strictSidebarFiltering' => $strictSidebarFiltering,
            ],

            'companyInfo' => $companyInfo,
            // Provide both `webSetting` and lowercase `websetting` keys so Inertia
            // partial reloads requesting `only: ['websetting']` will receive
            // the updated settings regardless of casing used by the client.
            'webSetting' => $webSetting,
            'websetting' => $webSetting,
            'pharmacyAlerts' => [
                'medicineExpiry' => $medicineExpiryAlert,
            ],
            'activityLogAlerts' => $activityLogAlert,
            'loginTexts' => [
                'banner' => env('LOGIN_BANNER', 'Hospital Management Suite'),
                'title' => env('LOGIN_TITLE', 'Welcome Back.'),
                'subtitle' => env('LOGIN_SUBTITLE', 'Continue with your secure account and manage all operations with confidence.'),
            ],
        ]);
    }
}
