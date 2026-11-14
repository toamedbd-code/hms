<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SettingsMenuAccessForAllRolesSeeder extends Seeder
{
    public function run(): void
    {
        $settingsMenu = Menu::query()
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->where('permission_name', 'settings-management')
            ->first();

        if (!$settingsMenu) {
            return;
        }

        $permissionNames = collect(['settings-management'])
            ->merge(
                Menu::query()
                    ->where('parent_id', $settingsMenu->id)
                    ->whereNull('deleted_at')
                    ->where('status', 'Active')
                    ->pluck('permission_name')
            )
            ->map(fn ($name) => strtolower(trim((string) $name)))
            ->filter(fn ($name) => $name !== '' && $name !== 'b-kash-settings')
            ->unique()
            ->values();

        $availablePermissionNames = Permission::query()
            ->where('guard_name', 'admin')
            ->whereIn('name', $permissionNames)
            ->pluck('name')
            ->map(fn ($name) => strtolower(trim((string) $name)))
            ->values()
            ->all();

        if ($availablePermissionNames === []) {
            return;
        }

        Role::query()
            ->where('guard_name', 'admin')
            ->each(fn (Role $role) => $role->givePermissionTo($availablePermissionNames));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
