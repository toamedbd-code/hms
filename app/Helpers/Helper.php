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
    return number_format($amount, 2);
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

    if (str_starts_with($normalized, 'public/storage/')) {
        $normalized = substr($normalized, strlen('public/storage/'));
    } elseif (str_starts_with($normalized, 'storage/')) {
        $normalized = substr($normalized, strlen('storage/'));
    }

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

    return asset($normalized);
}

function getSideMenus($user)
{
    if (!$user) {
        return [];
    }

    // reload user roles/permissions to reflect recent changes
    try {
        $user = $user->fresh(['roles.permissions', 'permissions']);
    } catch (\Throwable $e) {
        // ignore and continue with provided user
    }

    $grantedPermissions = collect();
    try {
        // Normalize permission names to lowercase trimmed strings so
        // server-side matching is robust against case/whitespace differences.
        $grantedPermissions = $user->getAllPermissions()
            ->pluck('name')
            ->filter()
            ->map(function ($n) {
                return strtolower(trim((string) $n));
            })->unique()->values();
    } catch (\Throwable $e) {
        $grantedPermissions = collect();
    }

    $hasMenuPermission = function ($permissionName) use ($grantedPermissions) {
        $permissionName = trim((string) $permissionName);

        // Treat empty permission as not granted — visibility should be
        // determined by whether permitted children exist. This prevents
        // menus with no explicit permission from appearing for users who
        // don't actually hold any relevant permissions.
        if ($permissionName === '') {
            return false;
        }

        return $grantedPermissions->contains(strtolower($permissionName));
    };

    $menus = Menu::with(['childrens' => function ($q) {
            $q->whereNull('deleted_at')
                ->where('status', 'Active')
                ->orderBy('sorting', 'ASC')
                ->orderBy('id', 'ASC');
        }])
        ->whereNull('parent_id')
        ->whereNull('deleted_at')
        ->where('status', 'Active')
        // order by sorting then id to ensure deterministic parent ordering
        // (id keeps original insertion order so Dashboard remains first)
        ->orderBy('sorting', 'ASC')
        ->orderBy('id', 'ASC')
        ->get();
    // Per-user strict filtering control. When an admin's email appears in
    // the `SIDEBAR_STRICT_EMAILS` env list, we disable any "show-everything"
    // fallbacks so that the returned menus strictly reflect assigned
    // permissions/modules for that user.
    $strictEmailsEnv = env('SIDEBAR_STRICT_EMAILS', '');
    $strictEmails = array_filter(array_map('trim', explode(',', (string) $strictEmailsEnv)));
    $userEmailLower = null;
    try {
        $userEmailLower = strtolower(trim((string) ($user->email ?? '')));
    } catch (\Throwable $e) {
        $userEmailLower = null;
    }
    $strictFiltering = $userEmailLower && in_array($userEmailLower, array_map('strtolower', $strictEmails), true);
    // Config-driven override: optionally force full unfiltered menus for
    // debugging or for specific users. Controlled via config/sidebar.php
    // or environment variables (FORCE_FULL_SIDEBAR, FORCE_FULL_SIDEBAR_EMAILS, etc.).
    try {
        $forceFull = config('sidebar.force_full_menus', env('FORCE_FULL_SIDEBAR', false));
        $forceForAll = config('sidebar.force_for_all', env('FORCE_FULL_SIDEBAR_FORCE_ALL', false));
        $allowDevs = config('sidebar.allow_developers', env('FORCE_FULL_SIDEBAR_ALLOW_DEVS', true));
        $emailsEnv = env('FORCE_FULL_SIDEBAR_EMAILS', '');
        $emails = array_filter(array_map('trim', explode(',', (string) $emailsEnv)));

        if ($forceFull && !$strictFiltering) {
            $shouldReturn = false;

            if ($forceForAll) {
                $shouldReturn = true;
            } else {
                $userEmail = null;
                try {
                    $userEmail = strtolower(trim((string) ($user->email ?? '')));
                } catch (\Throwable $e) {
                    $userEmail = null;
                }

                if ($userEmail && in_array($userEmail, array_map('strtolower', $emails), true)) {
                    $shouldReturn = true;
                }

                if (!$shouldReturn && $allowDevs && !$strictFiltering) {
                    try {
                        if (method_exists($user, 'hasRole') && $user->hasRole('developer')) {
                            $shouldReturn = true;
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }

            if ($shouldReturn) {
                return $menus->map(function ($m) {
                    return is_array($m) ? $m : $m->toArray();
                })->values();
            }
        }
    } catch (\Throwable $e) {
        // ignore override failures
    }

        // Developer full-menu bypass was historically unconditional. Restrict it
        // so it only applies when the sidebar debug override is explicitly enabled
        // (config/sidebar.force_full_menus + config/sidebar.allow_developers).
        try {
            $forceFull = config('sidebar.force_full_menus', env('FORCE_FULL_SIDEBAR', false));
            $allowDevs = config('sidebar.allow_developers', env('FORCE_FULL_SIDEBAR_ALLOW_DEVS', true));

            if ($forceFull && $allowDevs && !$strictFiltering && method_exists($user, 'hasRole') && $user->hasRole('developer')) {
                    // Allow developers to see all menus only when the override is active.
                    $blockedSubstrings = [];

                    $devMenus = $menus->map(function ($menu) use ($blockedSubstrings) {
                        $arr = is_array($menu) ? $menu : $menu->toArray();

                        if (isset($arr['childrens']) && is_array($arr['childrens'])) {
                            usort($arr['childrens'], function ($a, $b) {
                                $sa = isset($a['sorting']) ? (int) $a['sorting'] : 0;
                                $sb = isset($b['sorting']) ? (int) $b['sorting'] : 0;
                                if ($sa !== $sb) return $sa - $sb;
                                $ida = isset($a['id']) ? (int) $a['id'] : 0;
                                $idb = isset($b['id']) ? (int) $b['id'] : 0;
                                return $ida - $idb;
                            });

                            // Filter out invoice-design children by name or route substring
                            $arr['childrens'] = array_values(array_filter($arr['childrens'], function ($child) use ($blockedSubstrings) {
                                $cname = strtolower(trim((string) ($child['name'] ?? '')));
                                $croute = strtolower(trim((string) ($child['route'] ?? '')));
                                foreach ($blockedSubstrings as $s) {
                                    if ($s !== '' && (strpos($cname, $s) !== false || strpos($croute, $s) !== false)) {
                                        return false;
                                    }
                                }
                                return true;
                            }));
                        }

                        return $arr;
                    })->sortBy(function ($m) {
                        $sorting = (int) ($m['sorting'] ?? 0);
                        $id = (int) ($m['id'] ?? 0);
                        return sprintf('%05d-%010d', $sorting, $id);
                    })->values();

                    // Also drop any top-level menus that reference invoice-design
                    $devMenus = $devMenus->reject(function ($m) use ($blockedSubstrings) {
                        $name = is_array($m) ? strtolower(trim((string) ($m['name'] ?? ''))) : strtolower(trim((string) ($m->name ?? '')));
                        $route = is_array($m) ? strtolower(trim((string) ($m['route'] ?? ''))) : strtolower(trim((string) ($m->route ?? '')));
                        foreach ($blockedSubstrings as $s) {
                            if ($s !== '' && (strpos($name, $s) !== false || strpos($route, $s) !== false)) return true;
                        }
                        return false;
                    })->values();

                    return $devMenus;
                }
        } catch (\Throwable $e) {
            // ignore and fall back to normal permission/module filtering below
        }

    $normalizeRoute = function ($route) {
        $route = trim((string) $route);

        $aliases = [
            'backend.pharmacy.supplier.payment' => 'backend.supplierpayment.index',
            'admin.attendance.devices' => 'backend.attendance.devices',
        ];

        return $aliases[$route] ?? $route;
    };

    $result = $menus->filter(function ($menu) use ($normalizeRoute, $hasMenuPermission) {
        $menuHasPermission = $hasMenuPermission($menu->permission_name ?? null);

        $menu->childrens = $menu->childrens->filter(function ($child) use ($hasMenuPermission) {
            return $hasMenuPermission($child->permission_name ?? null);
        })->reject(function ($child) {
            $name = strtolower(trim((string) ($child->name ?? '')));

            // Hard-remove requested menu labels regardless of language variations/casing.
            $blockedNames = [
                'পার্সেস প্রোডাক্ট',
                'পারছেস প্রোডাক্ট',
                'পারচেস প্রোডাক্ট',
                'প্রোডাক্ট ডেলিভারি',
                'product delivery',
                'product add',
            ];

            return in_array($name, $blockedNames, true);
        })->unique(function ($child) use ($normalizeRoute) {
            $name = strtolower(trim((string) ($child->name ?? '')));
            $route = $normalizeRoute($child->route ?? '');

            // Keep only one Supplier Payment entry even if route aliases differ.
            if (str_contains($name, 'supplier payment')) {
                return 'name:supplier-payment';
            }

            return $route !== '' ? ('route:' . $route) : ('name:' . $name);
        })->sortBy(function ($child) {
            $sorting = 0;
            $id = 0;
            if (is_array($child)) {
                $sorting = (int) ($child['sorting'] ?? 0);
                $id = (int) ($child['id'] ?? 0);
            } else {
                $sorting = (int) ($child->sorting ?? 0);
                $id = (int) ($child->id ?? 0);
            }

            return sprintf('%05d-%010d', $sorting, $id);
        })->values();

        $menuName = strtolower(trim((string) ($menu->name ?? '')));
        $isPurchaseMenu = str_contains($menuName, 'purchase') || str_contains($menuName, 'পার্সেস') || str_contains($menuName, 'পারচেস');

        if ($isPurchaseMenu) {
            $hasAddMedicine = $menu->childrens->contains(function ($child) {
                return strtolower(trim((string) ($child->name ?? ''))) === 'add medicine product';
            });

            $hasAddPurchase = $menu->childrens->contains(function ($child) {
                return strtolower(trim((string) ($child->name ?? ''))) === 'add purchase product';
            });

            $menu->childrens = $menu->childrens->reject(function ($child) use ($hasAddMedicine, $hasAddPurchase) {
                $name = strtolower(trim((string) ($child->name ?? '')));

                if ($hasAddMedicine && $name === 'edit medicine product') {
                    return true;
                }

                if ($hasAddPurchase && $name === 'edit purchase product') {
                    return true;
                }

                return false;
            })->values();
        }

        // hide parent menu if it has no permitted children and no route
        $hasChildren = (is_array($menu->childrens) ? count($menu->childrens) : $menu->childrens->count()) > 0;
        $route = trim((string) ($menu->route ?? ''));

        // Parent route should not be clickable when its own permission is unselected.
        if ($route !== '' && !$menuHasPermission) {
            if (is_array($menu)) {
                $menu['route'] = null;
                $route = '';
            } else {
                $menu->route = null;
                $route = '';
            }
        }

        // If there are no permitted children and no route, hide the parent only
        // when the current user also doesn't have the parent's permission.
        if (!$hasChildren && $route === '' && !$menuHasPermission) {
            return false;
        }

        return $menu;
    })->unique(function ($menu) {
        $route = trim((string) ($menu->route ?? ''));
        return $route !== '' ? ('route:' . $route) : ('name:' . trim((string) ($menu->name ?? '')));
    })->values();

    // Temporary safety: ensure Account Management parent menu appears only
    // for Admin users when either the admin has the parent permission or at
    // least one child permission is granted. This prevents non-admin roles
    // (e.g., Developer) from getting an always-visible Account Management
    // parent merely because they hold some child permissions.
    try {
        if (!$strictFiltering && method_exists($user, 'hasRole') && ($user->hasRole('Admin') || $user->hasRole('admin'))) {
            $accountName = 'Account Management';
            $already = $result->first(function ($m) use ($accountName) {
                $name = is_array($m) ? ($m['name'] ?? '') : ($m->name ?? '');
                return trim(strtolower((string) $name)) === trim(strtolower($accountName));
            });

            if (!$already) {
                $accountMenuModel = Menu::with('childrens')->where('name', $accountName)->first();
                if ($accountMenuModel) {
                    $menuArr = $accountMenuModel->toArray();
                    $children = collect($menuArr['childrens'] ?? [])->filter(function ($child) use ($hasMenuPermission) {
                        return $hasMenuPermission($child['permission_name'] ?? null);
                    })->sortBy(function ($child) {
                        $sorting = (int) ($child['sorting'] ?? 0);
                        $id = (int) ($child['id'] ?? 0);
                        return sprintf('%05d-%010d', $sorting, $id);
                    })->values()->toArray();

                    $menuArr['childrens'] = $children;

                    $hasParentPermission = $hasMenuPermission($menuArr['permission_name'] ?? null);
                    $hasAnyChildPerm = count($children) > 0;

                    if ($hasParentPermission || $hasAnyChildPerm) {
                        $result->push($menuArr);
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        // ignore temporary override failures
    }

    // Developer full-menu bypass: only apply when the sidebar debug override
    // is explicitly enabled (config/sidebar.force_full_menus +
    // config/sidebar.allow_developers). By default developers should see
    // only the menus granted via permissions so role-created developer
    // accounts behave like any other role.
    try {
        $forceFull = config('sidebar.force_full_menus', env('FORCE_FULL_SIDEBAR', false));
        $allowDevs = config('sidebar.allow_developers', env('FORCE_FULL_SIDEBAR_ALLOW_DEVS', false));

        if (!$strictFiltering && $forceFull && $allowDevs && method_exists($user, 'hasRole') && $user->hasRole('developer')) {
            return $result->values();
        }
    } catch (\Throwable $e) {
        // ignore failures; fall back to module filtering below
    }

    // Filter menus by assigned modules (if menus have module_slug set)
    try {
        try {
            $userModuleSlugs = $user->modules()->pluck('slug')->map(function ($s) {
                return trim(strtolower((string) $s));
            })->filter()->values();
        } catch (\Throwable $e) {
            $userModuleSlugs = collect();
        }

        $result = $result->filter(function ($menu) use ($userModuleSlugs) {
            $menuModule = '';
            if (is_array($menu)) {
                $menuModule = trim(strtolower((string) ($menu['module_slug'] ?? '')));
            } else {
                $menuModule = trim(strtolower((string) ($menu->module_slug ?? '')));
            }

            // keep menus without a module assignment
            if ($menuModule === '') {
                return true;
            }

            return $userModuleSlugs->contains($menuModule);
        })->values();
    } catch (\Throwable $e) {
        // ignore filtering errors and return as-is
    }

    // Previously this code removed InvoiceDesign-related menu entries.
    // Keep the blocked substrings empty so Invoice Design menus are allowed.
    try {
        $blockedSubstrings = [];

        // strip any children matching blocked substrings for arrays and objects
        $result = $result->map(function ($menu) use ($blockedSubstrings, $normalizeRoute) {
            if (is_array($menu)) {
                $children = $menu['childrens'] ?? [];
                $menu['childrens'] = array_values(array_filter($children, function ($child) use ($blockedSubstrings) {
                    $cn = strtolower(trim((string) ($child['name'] ?? '')));
                    $cr = strtolower(trim((string) ($child['route'] ?? '')));
                    foreach ($blockedSubstrings as $s) {
                        if ($s !== '' && (strpos($cn, $s) !== false || strpos($cr, $s) !== false)) {
                            return false;
                        }
                    }
                    return true;
                }));

                return $menu;
            } else {
                try {
                    $menu->childrens = $menu->childrens->filter(function ($child) use ($blockedSubstrings) {
                        $cn = strtolower(trim((string) ($child->name ?? '')));
                        $cr = strtolower(trim((string) ($child->route ?? '')));
                        foreach ($blockedSubstrings as $s) {
                            if ($s !== '' && (strpos($cn, $s) !== false || strpos($cr, $s) !== false)) {
                                return false;
                            }
                        }
                        return true;
                    })->values();
                } catch (\Throwable $e) {
                    // ignore
                }

                return $menu;
            }
        })->filter(function ($menu) use ($blockedSubstrings, $normalizeRoute) {
            $name = is_array($menu) ? strtolower(trim((string) ($menu['name'] ?? ''))) : strtolower(trim((string) ($menu->name ?? '')));
            $route = is_array($menu) ? strtolower(trim((string) ($menu['route'] ?? ''))) : strtolower(trim((string) ($menu->route ?? '')));

            foreach ($blockedSubstrings as $s) {
                if ($s !== '' && (strpos($name, $s) !== false || strpos($route, $s) !== false)) return false;
            }

            return true;
        })->values();
    } catch (\Throwable $e) {
        // ignore safety filter failures
    }

    // Ensure consistent ordering across users by sorting final menus by
    // the `sorting` field (works for both model instances and arrays).
    try {
        $result = $result->sortBy(function ($m) {
            $sorting = 0;
            $id = 0;
            if (is_array($m)) {
                $sorting = (int) ($m['sorting'] ?? 0);
                $id = (int) ($m['id'] ?? 0);
            } else {
                $sorting = (int) ($m->sorting ?? 0);
                $id = (int) ($m->id ?? 0);
            }

            // Composite key ensures stable ordering when `sorting` values tie.
            return sprintf('%05d-%010d', $sorting, $id);
        })->values();
    } catch (\Throwable $e) {
        // ignore sort failures and return as-is
    }

    // Final safety fallback: if no menus remained after filtering but the
    // current user is an admin, only return the full menus when an explicit
    // debug override is enabled (`force_full_menus`). This prevents admins
    // from implicitly seeing all menus when their role/permissions are
    // intended to limit visibility.
    try {
        if (!$strictFiltering && method_exists($result, 'isEmpty') && $result->isEmpty()) {
            try {
                $forceFull = config('sidebar.force_full_menus', env('FORCE_FULL_SIDEBAR', false));
                // Only apply the fallback when the override is explicitly enabled.
                if ($forceFull && method_exists($user, 'hasRole') && ($user->hasRole('Admin') || $user->hasRole('admin'))) {
                    return $menus->map(function ($m) {
                        return is_array($m) ? $m : $m->toArray();
                    })->values();
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
    } catch (\Throwable $e) {
        // ignore
    }

    return $result->values();
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
