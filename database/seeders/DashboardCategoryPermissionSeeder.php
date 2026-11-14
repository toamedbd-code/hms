<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\MainCategory;
use App\Models\TestCategory;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DashboardCategoryPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $dashboardPermission = Permission::firstOrCreate(
            [
                'name' => 'dashboard',
                'guard_name' => 'admin',
            ],
            [
                'parent_id' => null,
                'sorting' => 1,
            ]
        );
        if ($dashboardPermission->parent_id !== null) {
            $dashboardPermission->parent_id = null;
            $dashboardPermission->save();
        }

        $roles = Role::query()
            ->where('guard_name', 'admin')
            ->whereIn('name', ['Admin', 'developer'])
            ->get();

        $doctorVisitPermission = Permission::firstOrCreate(
            [
                'name' => 'dashboard-card-doctor-visit-appointment',
                'guard_name' => 'admin',
            ],
            [
                'parent_id' => $dashboardPermission->id,
                'sorting' => 2,
            ]
        );
        if (
            (int) ($doctorVisitPermission->parent_id ?? 0) !== (int) $dashboardPermission->id
            || (int) ($doctorVisitPermission->sorting ?? 0) !== 2
        ) {
            $doctorVisitPermission->parent_id = $dashboardPermission->id;
            $doctorVisitPermission->sorting = 2;
            $doctorVisitPermission->save();
        }

        if ($doctorVisitPermission->wasRecentlyCreated) {
            foreach ($roles as $role) {
                $role->givePermissionTo($doctorVisitPermission->name);
            }
        }

        TestCategory::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => strtolower($name))
            ->each(function (string $name) use ($dashboardPermission, $roles): void {
                $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
                if ($slug === '') {
                    return;
                }

                $permission = Permission::firstOrCreate(
                    [
                        'name' => 'dashboard-card-' . $slug . '-income',
                        'guard_name' => 'admin',
                    ],
                    [
                        'parent_id' => $dashboardPermission->id,
                        'sorting' => 1,
                    ]
                );

                if ((int) ($permission->parent_id ?? 0) !== (int) $dashboardPermission->id) {
                    $permission->parent_id = $dashboardPermission->id;
                    $permission->save();
                }

            });

        MainCategory::query()
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => strtolower($name))
            ->each(function (string $name) use ($dashboardPermission, $roles): void {
                $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
                if ($slug === '') {
                    return;
                }

                $permission = Permission::firstOrCreate(
                    [
                        'name' => 'dashboard-main-category-' . $slug . '-income',
                        'guard_name' => 'admin',
                    ],
                    [
                        'parent_id' => $dashboardPermission->id,
                        'sorting' => 1,
                    ]
                );

                if ((int) ($permission->parent_id ?? 0) !== (int) $dashboardPermission->id) {
                    $permission->parent_id = $dashboardPermission->id;
                    $permission->save();
                }

                if ($permission->wasRecentlyCreated) {
                    foreach ($roles as $role) {
                        $role->givePermissionTo($permission->name);
                    }
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}