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
        $adminUser = null;

        if (auth()->guard('admin')->check() && auth()->guard('admin')->user()->status == 'Active') {
            $adminUser = auth()->guard('admin')->user();

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

        $companyInfo = (session()->has('companyInfo')) ? session()->get('companyInfo') : Company::first();

        $webSetting = get_cached_web_setting();

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

        if (auth()->guard('admin')->check()) {
            /** @var \App\Models\Admin|null $adminUser */
            $adminUser = $adminUser ?: auth()->guard('admin')->user();
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
            $adminPermissions = $adminUser->getAllPermissions()->pluck('name')->unique()->values();
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
            ],

            'companyInfo' => $companyInfo,
            'webSetting' => $webSetting,
            'pharmacyAlerts' => [
                'medicineExpiry' => $medicineExpiryAlert,
            ],
            'activityLogAlerts' => $activityLogAlert,
        ]);
    }
}
