<?php

use App\Models\Menu;
use App\Models\WebSetting;
use Illuminate\Support\Facades\Cache;

function addDays($numOfDays, $date = null)
{
    $date = $date ? strtotime($date) : time();
    return date('Y-m-d', strtotime("+$numOfDays days", $date));
}

function subDays($numOfDays, $date = null)
{
    $date = $date ? strtotime($date) : time();
    return date('Y-m-d', strtotime("-$numOfDays days", $date));
}
function numOfDay($date)
{
    return date('d', strtotime($date));
}
function numOfMonth($date)
{
    return date('m', strtotime($date));
}
function numOfYear($date)
{
    return date('Y', strtotime($date));
}
function getDay($date)
{
    return date('l', strtotime($date));
}
function getMonth($date)
{
    return date('F', strtotime($date));
}
function getYear($date)
{
    return date('Y', strtotime($date));
}
function currentDate()
{
    return date('Y-m-d');
}
function timeStamp($date = null)
{
    $date = $date ? strtotime($date) : time();
    return  strtotime($date);
}
function currentTimeStamp()
{
    return date('Y-m-d H:i:s');
}
function dateFormat($date)
{
    return date('d F, Y', strtotime($date));
}
function dateFormatForDatabase($date)
{
    return date('Y-m-d', strtotime($date));
}
function dateForForm($date)
{
    return date('d-m-Y', strtotime($date));
}
function time24HoursFormat($time)
{
    return date('H:i:s', strtotime($time));
}
function time12HoursFormat($time)
{
    return date('h:i:s A', strtotime($time));
}
function logTime($time)
{
    return date('h:i A', strtotime($time));
}
function dateTime($date)
{
    return date('d F,Y h:i A', strtotime($date));
}
function dayWithDate($date)
{
    return date('d F, Y, l', strtotime($date));
}
function getActiveMenuClass($routeName)
{
    return (request()->fullUrl() == route($routeName ?? '')) ? 'active' : '';
}

function getYesNoBadge($status)
{
    return ($status == 'Active') ? 'badge-success text-dark' : 'badge-danger text-dark';
}
function getYesNoColor($status)
{
    return ($status == 'Active') ? 'text-success' : 'text-danger';
}

function getStatusBadge($status)
{
    if ($status == 'Active')
        return 'badge-success ';
    if ($status == 'Inactive')
        return 'badge-warning';
    if ($status == 'Deleted')
        return 'badge-danger ';
}


function getStatusText($status)
{
    if ($status == 'Active') {
        return '<span class="px-2 py-1 text-xs font-semibold text-white bg-green-500 rounded-full">' . $status . '</span>';
    } elseif ($status == 'Inactive') {
        return '<span class="px-2 py-1 text-xs font-semibold text-white bg-yellow-300 rounded-full">' . $status . '</span>';
    } elseif ($status == 'Deleted') {
        return '<span class="px-2 py-1 text-xs font-semibold text-white bg-red-500 rounded-full">' . $status . '</span>';
    } elseif ($status == 'Pending') {
        return '<span class="px-2 py-1 text-xs font-semibold text-white bg-blue-500 rounded-full">' . $status . '</span>';
    } elseif ($status == 'Paid') {
        return '<span class="px-2 py-1 text-xs font-semibold text-white bg-green-600 rounded-full">' . $status . '</span>';
    } elseif ($status == 'Partial') {
        return '<span class="px-2 py-1 text-xs font-semibold text-white rounded-full bg-amber-500">' . $status . '</span>';
    } elseif ($status == 'Due') {
        return '<span class="px-2 py-1 text-xs font-semibold text-white bg-red-500 rounded-full">' . $status . '</span>';
    } elseif ($status == 'Approved') {
        return '<span class="px-2 py-1 text-xs font-semibold text-white bg-green-500 rounded-full">' . $status . '</span>';
    } elseif ($status == 'Rejected') {
        return '<span class="px-2 py-1 text-xs font-semibold text-white bg-red-500 rounded-full">' . $status . '</span>';
    } elseif ($status == 'In') {
        return '<span class="px-2 py-1 text-xs font-semibold text-white bg-green-500 rounded-full">' . $status . '</span>';
    } elseif ($status == 'Out') {
        return '<span class="px-2 py-1 text-xs font-semibold text-white bg-red-500 rounded-full">' . $status . '</span>';
    } elseif ($status == 'Present') {
        return '<span class="px-2 py-1 text-xs font-semibold text-white bg-green-500 rounded-full">' . $status . '</span>';
    } elseif ($status == 'Absent') {
        return '<span class="px-2 py-1 text-xs font-semibold text-white bg-red-500 rounded-full">' . $status . '</span>';
    } elseif ($status == 'Backward') {
        return '<span class="px-2 py-1 text-xs font-semibold text-white rounded-full bg-amber-500">' . $status . '</span>';
    } else {
        return '<span class="px-2 py-1 text-xs font-semibold text-white bg-gray-500 rounded-full">' . $status . '</span>';
    }
}
function yesNoTextWithBadge($status)
{
    if ($status == 1) {
        return '<span class="px-2 py-1 text-xs font-semibold text-white bg-green-500 rounded-full">Yes</span>';
    } elseif ($status == 0) {
        return '<span class="px-2 py-1 text-xs font-semibold text-white bg-red-500 rounded-full">No</span>';
    }
}

function getLinkLabel($linkText = null, $icon = null, $class = null)
{
    return '<span title="' . $linkText . '" class="' . $class . '  " >' . $icon . ' ' . $linkText . '</span>';
}

function getStatusChangeBtn($status)
{
    if ($status == 'Active')
        return 'btn-secondary ';
    if ($status == 'Inactive')
        return 'btn-success';
}
function getApproveBtn()
{
    return 'btn-success';
}
function getRejectBtn()
{
    return 'btn-danger';
}
function getApproveIcon()
{
    return 'check-circle';
}
function getRejectIcon()
{
    return 'x-circle';
}
function getStatusChangeIcon($status)
{
    return ($status == 'Active') ? "x-circle" : "check-circle";
}
function priceFormat($amount)
{
    return number_format((float) ($amount ?? 0), 2, '.', ',');
}

function getDaysNumber()
{
    return [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31];
}
function getRandomColor()
{
    return '#' . substr(str_shuffle("0123456789ABCDEF"), 0, 6);
}

function regeneratePagination($datas, $total, $perPage, $currentPage)
{
    $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
        $datas,
        $total,
        $perPage,
        $currentPage,
        ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
    );


    $query = request()->query();

    $paginator->appends($query);

    return $paginator;
}


function getDataFrom($datas)
{
    return ($datas->currentPage() - 1) * $datas->perPage() + 1;
}

function getDataTo($datas)
{
    return min(getDataFrom($datas) + $datas->perPage() - 1, $datas->total());
}

function rowClass($key)
{
    return (($key % 2) ? "even-row" : "odd-row");
}

function imageNotFound()
{
    return asset('/app-assets/images/no-image.png');
}

function publicStorageUrl($value)
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    $value = trim(str_replace('\\', '/', $value));

    if (
        str_starts_with($value, 'http://')
        || str_starts_with($value, 'https://')
        || str_starts_with($value, 'data:')
    ) {
        return $value;
    }

    $normalized = ltrim($value, '/');
    $normalized = preg_replace('#^(?:storage/app/public/|public/storage/|storage/)#', '', $normalized);

    if ($normalized === '') {
        return null;
    }

    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($normalized)) {
        $publicStorageFile = public_path('storage/' . $normalized);

        if (is_file($publicStorageFile)) {
            return asset('storage/' . $normalized);
        }

        return route('backend.public.storage.file', ['path' => $normalized]);
    }

    $publicStorageFile = public_path('storage/' . $normalized);
    if (is_file($publicStorageFile)) {
        return asset('storage/' . $normalized);
    }

    $publicFile = public_path($normalized);
    if (is_file($publicFile)) {
        return asset($normalized);
    }

    return asset($normalized);
}

function getSideMenus($user)
{
    if (!$user) {
        return [];
    }

    $grantedPermissions = collect();
    try {
        $grantedPermissions = $user->getAllPermissions()
            ->pluck('name')
            ->filter()
            ->map(function ($name) {
                return strtolower(trim((string) $name));
            })
            ->unique()
            ->values();
    } catch (\Throwable $e) {
        $grantedPermissions = collect();
    }

    $hasMenuPermission = function ($permissionName) use ($grantedPermissions) {
        $permissionName = strtolower(trim((string) $permissionName));
        if ($permissionName === '') {
            return false;
        }

        return $grantedPermissions->contains($permissionName);
    };

    $routeExists = function ($routeName) {
        $routeName = trim((string) $routeName);
        if ($routeName === '') {
            return false;
        }

        $candidates = [$routeName];
        if (!str_starts_with($routeName, 'backend.')) {
            $candidates[] = 'backend.' . $routeName;
        }
        if (str_starts_with($routeName, 'backend.')) {
            $candidates[] = str_replace('backend.', 'backend.backend.', $routeName);
        }

        foreach ($candidates as $candidate) {
            if (\Illuminate\Support\Facades\Route::has($candidate)) {
                return true;
            }
        }

        $candidateNames = collect($candidates)
            ->map(fn ($candidate) => strtolower($candidate));

        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            $routeName = $route->getName();
            if ($routeName && $candidateNames->contains(strtolower($routeName))) {
                return true;
            }
        }

        return false;
    };

    $menus = Menu::with(['childrens' => function ($query) {
        $query->whereNull('deleted_at')
            ->where('status', 'Active')
            ->orderBy('sorting', 'ASC')
            ->orderBy('id', 'ASC');
    }])
        ->whereNull('parent_id')
        ->whereNull('deleted_at')
        ->where('status', 'Active')
        ->orderBy('sorting', 'ASC')
        ->orderBy('id', 'ASC')
        ->get();

    if ($menus->isEmpty()) {
        return [];
    }

    $normalizedMenus = $menus->map(function ($menu) {
        $menuArray = is_array($menu) ? $menu : $menu->toArray();
        $menuArray['childrens'] = collect($menuArray['childrens'] ?? [])
            ->values()
            ->all();

        return $menuArray;
    })->values();

    $hasAnyPermission = $grantedPermissions->isNotEmpty();

    $isDeveloper = \App\Support\DefaultDeveloperManager::isDeveloper($user);

    $normalizedMenus = $normalizedMenus->unique(function ($menu) {
        $name = strtolower(trim((string) ($menu['name'] ?? '')));
        $route = strtolower(trim((string) ($menu['route'] ?? '')));
        $permission = strtolower(trim((string) ($menu['permission_name'] ?? '')));

        if ($name !== '') {
            return 'n:' . $name;
        }
        if ($route !== '') {
            return 'r:' . $route;
        }

        return 'p:' . $permission;
    })->values();

    $result = $normalizedMenus->map(function ($menu) use ($hasMenuPermission, $hasAnyPermission, $routeExists, $isDeveloper) {
        $menuArray = is_array($menu) ? $menu : (array) $menu;
        $children = collect($menuArray['childrens'] ?? []);
        $menuPermissionName = strtolower(trim((string) ($menuArray['permission_name'] ?? $menuArray['permission'] ?? '')));
        $hasParentPermission = $menuPermissionName !== '' && $hasAnyPermission && $hasMenuPermission($menuPermissionName);

        $filteredChildren = $children->reject(function ($child) {
            $route = trim((string) ($child['route'] ?? ''));
            $name = strtolower(trim((string) ($child['name'] ?? '')));

            return ($route === 'backend.websetting.create' && $name === 'cms setting')
                || $route === 'backend.sitepurchase.index';
        })->unique(function ($child) {
            $name = strtolower(trim((string) ($child['name'] ?? '')));
            $route = strtolower(trim((string) ($child['route'] ?? '')));

            return $name !== '' ? 'n:' . $name : 'r:' . $route;
        })->filter(function ($child) use ($hasMenuPermission, $hasAnyPermission, $hasParentPermission, $routeExists, $isDeveloper) {
            $permissionName = strtolower(trim((string) ($child['permission_name'] ?? $child['permission'] ?? '')));
            $route = trim((string) ($child['route'] ?? ''));

            if (in_array($route, ['backend.settings.payment.bkash', 'settings.payment.bkash'], true) && !$isDeveloper) {
                return false;
            }

            if ($permissionName !== '' && $hasAnyPermission && !$hasMenuPermission($permissionName)) {
                return false;
            }

            if ($permissionName === '' && $hasAnyPermission && !$hasParentPermission) {
                return false;
            }

            if ($route === '') {
                return false;
            }

            return $routeExists($route);
        })->values()->all();

        if ($menuPermissionName !== '' && $hasAnyPermission && !$hasMenuPermission($menuPermissionName) && empty($filteredChildren)) {
            return null;
        }

        $route = trim((string) ($menuArray['route'] ?? ''));
        if ($route === '' && empty($filteredChildren)) {
            return null;
        }

        $menuArray['childrens'] = $filteredChildren;
        return $menuArray;
    })->filter()->values();

    $result = filterAddonModuleMenus($result, $user, $isDeveloper);

    if ($result->isNotEmpty()) {
        return $result;
    }

    $fallbackMenus = $normalizedMenus->filter(function ($menu) use ($routeExists) {
        $route = trim((string) ($menu['route'] ?? ''));
        if ($route !== '' && $routeExists($route)) {
            return true;
        }

        return trim((string) ($menu['name'] ?? '')) !== '';
    })->values();

    if ($fallbackMenus->isNotEmpty()) {
        return filterAddonModuleMenus($fallbackMenus, $user, $isDeveloper);
    }

    return [
        [
            'id' => 0,
            'name' => 'Dashboard',
            'route' => 'backend.dashboard',
            'permission_name' => 'dashboard',
            'childrens' => [],
            'status' => 'Active',
        ],
    ];
}

function addon_module_slugs(): array
{
    return ['pharmacy', 'prescription'];
}

function user_assigned_module_slugs($user)
{
    if (!$user || !method_exists($user, 'modules')) {
        return collect();
    }

    try {
        return $user->modules()
            ->pluck('slug')
            ->map(fn ($slug) => strtolower(trim((string) $slug)))
            ->filter()
            ->unique()
            ->values();
    } catch (\Throwable $e) {
        return collect();
    }
}

function user_has_module($user, string $slug): bool
{
    $slug = strtolower(trim($slug));
    if ($slug === '') {
        return true;
    }

    if (\App\Support\DefaultDeveloperManager::isDeveloper($user)) {
        return true;
    }

    $assigned = user_assigned_module_slugs($user);
    if ($assigned->isEmpty()) {
        return true;
    }

    return $assigned->contains($slug);
}

function user_is_pharmacy_only($user): bool
{
    return user_dashboard_department_slug($user) === 'pharmacy';
}

function user_dashboard_department_slug($user): ?string
{
    if (!$user) {
        return null;
    }

    if (\App\Support\DefaultDeveloperManager::isDeveloper($user)) {
        return null;
    }

    $roleNames = method_exists($user, 'getRoleNames')
        ? $user->getRoleNames()->map(fn ($name) => strtolower(trim((string) $name)))
        : collect();

    if ($roleNames->intersect(['admin', 'administration', 'super-admin', 'super admin', 'super_admin', 'developer'])->isNotEmpty()) {
        return null;
    }

    $departmentName = strtolower(trim((string) ($user->details?->department?->name ?? '')));
    foreach (['pharmacy', 'pathology', 'radiology'] as $department) {
        if (str_contains($departmentName, $department)) {
            return $department;
        }
    }

    foreach ($roleNames as $roleName) {
        if (str_contains($roleName, 'pharmacy') || str_contains($roleName, 'pharmacist')) {
            return 'pharmacy';
        }
        if (str_contains($roleName, 'pathology') || str_contains($roleName, 'pathologist')) {
            return 'pathology';
        }
        if (str_contains($roleName, 'radiology') || str_contains($roleName, 'radiologist')) {
            return 'radiology';
        }
    }

    $assignedModules = user_assigned_module_slugs($user);
    if ($assignedModules->isNotEmpty()) {
        if ($assignedModules->contains('pharmacy')
            && $assignedModules->diff(['pharmacy', 'prescription'])->isEmpty()) {
            return 'pharmacy';
        }
    }

    return null;
}

function menu_module_slug($menu): string
{
    $menuArray = is_array($menu) ? $menu : (array) $menu;
    $slug = strtolower(trim((string) ($menuArray['module_slug'] ?? '')));
    if ($slug !== '') {
        return $slug;
    }

    $haystack = strtolower(trim(implode(' ', array_filter([
        (string) ($menuArray['route'] ?? ''),
        (string) ($menuArray['permission_name'] ?? $menuArray['permission'] ?? ''),
        (string) ($menuArray['name'] ?? ''),
    ]))));

    if ($haystack === '') {
        return '';
    }

    if (str_contains($haystack, 'prescription')) {
        return 'prescription';
    }

    if (
        str_contains($haystack, 'pharmacy')
        || str_contains($haystack, 'medicine')
        || str_contains($haystack, 'supplierpayment')
        || str_contains($haystack, 'productreturn')
        || str_contains($haystack, 'supplier-payment')
        || str_contains($haystack, 'product-return')
    ) {
        return 'pharmacy';
    }

    return '';
}

function filterAddonModuleMenus($menus, $user, bool $isDeveloper = false)
{
    $menus = collect($menus);
    if ($isDeveloper || $menus->isEmpty()) {
        return $menus->values();
    }

    $assigned = user_assigned_module_slugs($user);
    if ($assigned->isEmpty()) {
        return $menus->values();
    }

    $addonSlugs = addon_module_slugs();

    return $menus->map(function ($menu) use ($assigned, $addonSlugs) {
        $menuArray = is_array($menu) ? $menu : (array) $menu;
        $slug = menu_module_slug($menuArray);

        if (in_array($slug, $addonSlugs, true) && !$assigned->contains($slug)) {
            return null;
        }

        $children = collect($menuArray['childrens'] ?? [])
            ->filter(function ($child) use ($assigned, $addonSlugs, $slug) {
                $childSlug = menu_module_slug($child) ?: $slug;
                if (in_array($childSlug, $addonSlugs, true) && !$assigned->contains($childSlug)) {
                    return false;
                }
                return true;
            })
            ->values()
            ->all();

        $menuArray['childrens'] = $children;
        return $menuArray;
    })->filter()->values();
}

function web_setting_prefix(string $field, string $default = ''): string
{
    static $prefixCache = [];

    if (array_key_exists($field, $prefixCache)) {
        return $prefixCache[$field];
    }

    try {
        $setting = get_cached_web_setting();
        $value = trim((string) ($setting?->{$field} ?? ''));
        $prefixCache[$field] = $value !== '' ? $value : $default;
    } catch (\Throwable $th) {
        $prefixCache[$field] = $default;
    }

    return $prefixCache[$field];
}

function web_setting_cache_key(): string
{
    return 'web_setting.active_or_latest';
}

function web_setting_cache_ttl_seconds(): int
{
    return max((int) config('cache.web_setting_ttl_seconds', 300), 10);
}

function get_cached_web_setting(bool $refresh = false): ?WebSetting
{
    $cacheKey = web_setting_cache_key();

    if ($refresh) {
        Cache::forget($cacheKey);
    }

    return Cache::remember($cacheKey, web_setting_cache_ttl_seconds(), function () {
        try {
            if (!app('db')->connection()->getSchemaBuilder()->hasTable('web_settings')) {
                return null;
            }
        } catch (\Throwable $exception) {
            return null;
        }

        $setting = WebSetting::query()
            ->where('status', 'Active')
            ->orderByDesc('id')
            ->first();

        if (!$setting) {
            $setting = WebSetting::query()->orderByDesc('id')->first();
        }

        return $setting;
    });
}

function forget_cached_web_setting(): void
{
    Cache::forget(web_setting_cache_key());
}

function prefixed_serial(string $prefixField, string $defaultPrefix, int|string $number, int $digits = 4): string
{
    $prefix = web_setting_prefix($prefixField, $defaultPrefix);
    return $prefix . str_pad((string) $number, $digits, '0', STR_PAD_LEFT);
}


function successResponse($message, $redirectUrl = null)
{
    if ($redirectUrl) {
        return redirect()->route($redirectUrl)->withSuccess($message);
    } else {
        return back()->withSuccess($message);
    }
}

function errorResponse($message, $redirectUrl = null)
{
    if ($redirectUrl) {
        return redirect()->route($redirectUrl)->withErrors($message);
    } else {
        return back()->withErrors($message);
    }
}

function warningResponse($message, $redirectUrl = null)
{
    if ($redirectUrl) {
        return redirect()->route($redirectUrl)->withWarning($message);
    } else {
        return back()->withWarning($message);
    }
}

function getPermissionName($value)
{
    return str_replace('-', ' ', strtolower($value));
}
function monthList()
{
    return [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];
}

function yearsBetween($minYear, $maxYear)
{
    $years = [];

    for ($year = $minYear; $year <= $maxYear; $year++) {
        $years[] = $year;
    }
    return $years;
}

function camelCaseToSmallLetters($words)
{
    return strtolower(preg_replace_callback('/(?<!\b)[A-Z]/', function ($match) {
        return '_' . strtolower($match[0]);
    }, $words));
}

function camelCaseToUpperLetters($str)
{
    return ucfirst(str_replace(['_', '-'], '', lcfirst(ucwords($str, '_-'))));
}

function camelCaseVariable($string)
{
    $words = explode(' ', ucwords(strtolower($string)));
    $firstWord = array_shift($words);
    return lcfirst($firstWord) . implode('', $words);
}

function camelCase($str) {
    return lcfirst(str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $str))));
}
