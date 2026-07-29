<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SidebarPermissionStabilitySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->normalizeKeyMenuMappings();
            $this->normalizeMenuSorting();
            $this->syncPermissionTreeFromMenus();
            $this->copyLegacyRolePermissions();
        });

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // ignore cache-reset errors
        }
    }

    private function normalizeKeyMenuMappings(): void
    {
        $permissionParents = [
            'settings-management' => 1,
            'account-management' => 1,
            'pharmacy-management' => 1,
            'report-management' => 1,
        ];

        foreach ($permissionParents as $parentName => $sorting) {
            $this->ensurePermission($parentName, null, $sorting);
        }

        $settingsRoutePermissionMap = [
            'backend.websetting.section.cms' => ['cms-setting', 1],
            'backend.websetting.section.general' => ['general-setting', 2],
            'backend.websetting.section.prefix' => ['prefix-setting', 3],
            'backend.websetting.section.sms' => ['sms-setting', 4],
            'backend.websetting.section.other' => ['other-setting', 5],
            'backend.websetting.section.sidebar' => ['sidebar-setting', 6],
            'backend.settings.payment.bkash' => ['b-kash-settings', 7],
            'backend.activity-logs.index' => ['activity-logs', 8],
            'backend.activity-logs.print' => ['activity-logs-print', 9],
        ];

        foreach ($settingsRoutePermissionMap as $route => [$permissionName, $sorting]) {
            $this->ensurePermission($permissionName, 'settings-management', $sorting);

            Menu::query()
                ->where('route', $route)
                ->where('status', 'Active')
                ->whereNull('deleted_at')
                ->update(['permission_name' => $permissionName]);
        }

        $this->ensurePermission('billing', null, 2);
        $this->ensurePermission('cash-counter', 'billing', 8);
        Menu::query()
            ->where('route', 'backend.cash-counter.index')
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->update(['permission_name' => 'cash-counter', 'parent_id' => null, 'name' => 'Cash Counter', 'icon' => 'credit-card']);

        $billingMenu = Menu::query()
            ->whereNull('parent_id')
            ->where(function ($query) {
                $query->where('route', 'backend.billing.Page')
                    ->orWhere('route', 'backend.billing.index')
                    ->orWhere('route', 'billing.Page')
                    ->orWhere('name', 'Billing')
                    ->orWhere('permission_name', 'billing');
            })
            ->first();

        if (!$billingMenu) {
            $billingMenu = new Menu();
            $billingMenu->sorting = (int) (Menu::query()->whereNull('parent_id')->max('sorting') ?? 0) + 1;
        }

        $billingMenu->parent_id = null;
        $billingMenu->name = 'Billing';
        $billingMenu->icon = 'billing';
        $billingMenu->route = 'backend.billing.Page';
        $billingMenu->description = null;
        $billingMenu->permission_name = 'billing';
        $billingMenu->status = 'Active';
        $billingMenu->deleted_at = null;
        if (empty($billingMenu->sorting)) {
            $billingMenu->sorting = 1;
        }
        $billingMenu->save();

        Menu::query()
            ->where('route', 'backend.cash-counter.index')
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->update(['parent_id' => null]);

        $this->ensurePermission('vendor-payment-list', 'account-management', 2);
        Menu::query()
            ->where('route', 'backend.accounts.vendor-payment.index')
            ->where('name', 'Vendor Payment')
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->update(['permission_name' => 'vendor-payment-list']);

        $this->ensurePermission('journal-entry', 'account-management', 3);
        $this->ensurePermission('trial-balance', 'account-management', 4);
        $this->ensurePermission('profit-loss', 'account-management', 5);
        $this->ensurePermission('balance-sheet', 'account-management', 6);
        $this->ensurePermission('cash-flow', 'account-management', 7);
        $accountParent = Menu::query()
            ->whereNull('parent_id')
            ->where(function ($query) {
                $query->where('name', 'Account Management')
                    ->orWhere('permission_name', 'account-management');
            })
            ->first();

        if ($accountParent) {
            $this->ensureAccountManagementMenu(
                $accountParent,
                'Journal Entries',
                'book',
                'backend.journal-entry.index',
                'journal-entry'
            );
            $this->ensureAccountManagementMenu(
                $accountParent,
                'Trial Balance',
                'bar-chart-2',
                'backend.accounts.trial-balance',
                'trial-balance'
            );
            $this->ensureAccountManagementMenu(
                $accountParent,
                'Profit & Loss',
                'trending-up',
                'backend.accounts.profit-loss',
                'profit-loss'
            );
            $this->ensureAccountManagementMenu(
                $accountParent,
                'Balance Sheet',
                'scale',
                'backend.accounts.balance-sheet',
                'balance-sheet'
            );
            $this->ensureAccountManagementMenu(
                $accountParent,
                'Cash Flow',
                'activity',
                'backend.accounts.cash-flow',
                'cash-flow'
            );
        }

        $this->ensurePermission('report-summary', 'report-management', 4);
        Menu::query()
            ->where('route', 'backend.report-summary.index')
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->update(['permission_name' => 'report-summary']);

        $this->ensurePermission('supplier-payment-list', 'pharmacy-management', 5);
        $this->ensurePermission('activity-log-view', 'account-management', 1);

        $productReturn = Menu::query()
            ->where('name', 'Product Return')
            ->whereIn('route', ['backend.pharmacy.return.index', 'backend.productreturn.index'])
            ->first();

        if ($productReturn && (string) $productReturn->route !== 'backend.productreturn.index') {
            $productReturn->route = 'backend.productreturn.index';
            $productReturn->save();
        }

        $pharmacyParent = Menu::query()
            ->whereNull('parent_id')
            ->where('name', 'Pharmacy')
            ->where('permission_name', 'pharmacy-bill-list')
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->first();

        if ($pharmacyParent) {
            if ((string) $pharmacyParent->route !== 'backend.pharmacybill.index') {
                $pharmacyParent->route = 'backend.pharmacybill.index';
                $pharmacyParent->save();
            }

            Menu::query()
                ->where('parent_id', $pharmacyParent->id)
                ->whereIn('route', [
                    'backend.pharmacybill.index',
                    'backend.accounts.audit',
                    'backend.accounts.vendor-payment.index',
                ])
                ->where('status', 'Active')
                ->whereNull('deleted_at')
                ->update(['status' => 'Inactive']);
        }
    }

    private function normalizeMenuSorting(): void
    {
        $grouped = Menu::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->orderByRaw('COALESCE(parent_id, 0) ASC')
            ->orderBy('sorting', 'ASC')
            ->orderBy('id', 'ASC')
            ->get(['id', 'parent_id', 'sorting'])
            ->groupBy(function ($menu) {
                return (string) ($menu->parent_id ?? 'root');
            });

        foreach ($grouped as $siblings) {
            $serial = 1;
            foreach ($siblings as $menu) {
                if ((int) ($menu->sorting ?? 0) !== $serial) {
                    Menu::query()->where('id', $menu->id)->update(['sorting' => $serial]);
                }
                $serial++;
            }
        }
    }

    private function syncPermissionTreeFromMenus(): void
    {
        $menus = Menu::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->orderBy('parent_id')
            ->orderBy('sorting')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'permission_name', 'sorting']);

        $permissionByName = Permission::query()
            ->where('guard_name', 'admin')
            ->get(['id', 'name', 'parent_id', 'sorting'])
            ->keyBy(function ($permission) {
                return strtolower(trim((string) $permission->name));
            });

        $menuById = $menus->keyBy('id');
        $appliedPermissionNames = [];

        foreach ($menus as $menu) {
            $permissionName = strtolower(trim((string) $menu->permission_name));
            if ($permissionName === '') {
                continue;
            }

            if (!isset($permissionByName[$permissionName])) {
                $permissionByName[$permissionName] = Permission::query()->create([
                    'name' => $permissionName,
                    'guard_name' => 'admin',
                    'parent_id' => null,
                    'sorting' => (int) ($menu->sorting ?? 1),
                ]);
            }

            if (isset($appliedPermissionNames[$permissionName])) {
                continue;
            }

            $permission = $permissionByName[$permissionName];
            $targetParentId = null;

            if (!empty($menu->parent_id) && isset($menuById[$menu->parent_id])) {
                $parentMenu = $menuById[$menu->parent_id];
                $parentPermissionName = strtolower(trim((string) $parentMenu->permission_name));

                if ($parentPermissionName !== '' && !isset($permissionByName[$parentPermissionName])) {
                    $permissionByName[$parentPermissionName] = Permission::query()->create([
                        'name' => $parentPermissionName,
                        'guard_name' => 'admin',
                        'parent_id' => null,
                        'sorting' => (int) ($parentMenu->sorting ?? 1),
                    ]);
                }

                if ($parentPermissionName !== '' && isset($permissionByName[$parentPermissionName])) {
                    $targetParentId = $permissionByName[$parentPermissionName]->id;
                }
            }

            if ((int) ($targetParentId ?? 0) === (int) $permission->id) {
                $targetParentId = null;
            }

            $targetSorting = (int) ($menu->sorting ?? 1);
            $needsUpdate = ((int) ($permission->parent_id ?? 0) !== (int) ($targetParentId ?? 0))
                || ((int) ($permission->sorting ?? 0) !== $targetSorting);

            if ($needsUpdate) {
                Permission::query()->where('id', $permission->id)->update([
                    'parent_id' => $targetParentId,
                    'sorting' => $targetSorting,
                ]);
                $permission->parent_id = $targetParentId;
                $permission->sorting = $targetSorting;
            }

            $appliedPermissionNames[$permissionName] = true;
        }

        $actionSuffixes = ['-status', '-create', '-edit', '-delete'];
        $permissionByName = Permission::query()
            ->where('guard_name', 'admin')
            ->get(['id', 'name', 'parent_id', 'sorting'])
            ->keyBy(function ($permission) {
                return strtolower(trim((string) $permission->name));
            });

        foreach ($permissionByName as $name => $permission) {
            foreach ($actionSuffixes as $suffix) {
                if (!str_ends_with($name, $suffix)) {
                    continue;
                }

                $baseName = substr($name, 0, -strlen($suffix));
                if ($baseName === '' || !isset($permissionByName[$baseName])) {
                    continue;
                }

                $basePermission = $permissionByName[$baseName];
                $targetParentId = $basePermission->id;
                $targetSorting = (int) ($basePermission->sorting ?? 1);

                $needsUpdate = ((int) ($permission->parent_id ?? 0) !== (int) $targetParentId)
                    || ((int) ($permission->sorting ?? 0) !== $targetSorting);

                if ($needsUpdate) {
                    Permission::query()->where('id', $permission->id)->update([
                        'parent_id' => $targetParentId,
                        'sorting' => $targetSorting,
                    ]);
                }
                break;
            }
        }

        $groupedPermissions = Permission::query()
            ->where('guard_name', 'admin')
            ->orderByRaw('COALESCE(parent_id, 0) ASC')
            ->orderBy('sorting', 'ASC')
            ->orderBy('id', 'ASC')
            ->get(['id', 'parent_id', 'sorting'])
            ->groupBy(function ($permission) {
                return (string) ($permission->parent_id ?? 'root');
            });

        foreach ($groupedPermissions as $siblings) {
            $serial = 1;
            foreach ($siblings as $permission) {
                if ((int) ($permission->sorting ?? 0) !== $serial) {
                    Permission::query()->where('id', $permission->id)->update(['sorting' => $serial]);
                }
                $serial++;
            }
        }
    }

    private function copyLegacyRolePermissions(): void
    {
        $mappings = [
            ['websetting-add', 'cms-setting'],
            ['websetting-add', 'general-setting'],
            ['websetting-add', 'prefix-setting'],
            ['websetting-add', 'sms-setting'],
            ['websetting-add', 'other-setting'],
            ['websetting-add', 'b-kash-settings'],
            ['activity-log-view', 'activity-logs'],
            ['activity-log-view', 'activity-logs-print'],
            ['supplier-payment-list', 'vendor-payment-list'],
            ['report-management', 'report-summary'],
            ['billing', 'cash-counter'],
            ['test-list', 'itemcharge-list'],
            ['test-list-create', 'itemcharge-list-create'],
            ['test-list-edit', 'itemcharge-list-edit'],
            ['test-list-delete', 'itemcharge-list-delete'],
            ['test-list-status', 'itemcharge-list-status'],
        ];

        foreach ($mappings as [$fromPermission, $toPermission]) {
            $this->copyRolePermission($fromPermission, $toPermission);
        }
    }

    private function copyRolePermission(string $fromPermission, string $toPermission): void
    {
        $from = Permission::query()->where('guard_name', 'admin')->where('name', $fromPermission)->first();
        $to = Permission::query()->where('guard_name', 'admin')->where('name', $toPermission)->first();

        if (!$from || !$to || (int) $from->id === (int) $to->id) {
            return;
        }

        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $from->id)
            ->pluck('role_id');

        foreach ($roleIds as $roleId) {
            $exists = DB::table('role_has_permissions')
                ->where('permission_id', $to->id)
                ->where('role_id', $roleId)
                ->exists();

            if (!$exists) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $to->id,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    private function ensurePermission(string $name, ?string $parentName, int $sorting): Permission
    {
        $permission = Permission::query()->firstOrCreate(
            ['name' => $name, 'guard_name' => 'admin'],
            ['parent_id' => null, 'sorting' => $sorting]
        );

        $parentId = null;
        if ($parentName) {
            $parent = Permission::query()->where('guard_name', 'admin')->where('name', $parentName)->first();
            $parentId = $parent ? (int) $parent->id : null;
        }

        $needsUpdate = ((int) ($permission->parent_id ?? 0) !== (int) ($parentId ?? 0))
            || ((int) ($permission->sorting ?? 0) !== $sorting);

        if ($needsUpdate) {
            $permission->parent_id = $parentId;
            $permission->sorting = $sorting;
            $permission->save();
        }

        return $permission;
    }

    private function ensureAccountManagementMenu(Menu $accountParent, string $name, string $icon, string $route, string $permissionName): void
    {
        $menu = Menu::query()
            ->where('parent_id', $accountParent->id)
            ->where(function ($query) use ($name, $route, $permissionName) {
                $query->where('route', $route)
                    ->orWhere('route', ltrim(str_replace('backend.', '', $route), '.'))
                    ->orWhere('name', $name)
                    ->orWhere('permission_name', $permissionName);
            })
            ->first();

        if (!$menu) {
            $menu = new Menu();
            $menu->sorting = (int) (Menu::query()->where('parent_id', $accountParent->id)->max('sorting') ?? 0) + 1;
        }

        $menu->parent_id = $accountParent->id;
        $menu->name = $name;
        $menu->icon = $icon;
        $menu->route = $route;
        $menu->description = null;
        $menu->permission_name = $permissionName;
        $menu->status = 'Active';
        $menu->deleted_at = null;
        if (empty($menu->sorting)) {
            $menu->sorting = 1;
        }
        $menu->save();
    }
}
