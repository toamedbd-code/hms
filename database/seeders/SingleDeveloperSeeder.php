<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin;
use App\Models\Permission;
use Spatie\Permission\Models\Role;

class SingleDeveloperSeeder extends Seeder
{
    public function run()
    {
        // Seed menus and related menu-sync seeders so PermissionSeeder can build full permission set
        $this->call([
            MenuSeeder::class,
            SettingsMenuSyncSeeder::class,
            QuickAccessMenuPermissionSyncSeeder::class,
            StoreManagementMenuSeeder::class,
        ]);

        // Create permissions based on menus
        $this->call([PermissionSeeder::class, PermissionFixSeeder::class, MenuPermissionCoverageSeeder::class, DutyRosterPermissionSeeder::class, SalarySheetPermissionSeeder::class]);

        // Ensure permission cache is cleared
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create or update developer role and mark it private
        $dev = Role::firstOrCreate(['name' => 'developer', 'guard_name' => 'admin']);
        try {
            $dev->is_private = true;
            $dev->save();
        } catch (\Throwable $e) {
            // ignore save errors
        }

        // Give developer role all permissions
        $allPermissions = Permission::pluck('name')->toArray();
        if (!empty($allPermissions)) {
            try {
                $dev->syncPermissions($allPermissions);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Create the single admin user (only if not exists)
        $email = env('SINGLE_DEV_EMAIL', 'toamedbd@gmail.com');
        $password = env('SINGLE_DEV_PASSWORD', 'zxczxc');

        if (!Admin::where('email', $email)->exists()) {
            $admin = Admin::create([
                'first_name' => 'Toamed',
                'last_name' => 'Admin',
                'email' => $email,
                'phone' => '01700000000',
                'password' => $password,
                'role_id' => $dev->id,
                'status' => 'Active',
            ]);

            if ($admin) {
                try {
                    $admin->assignRole($dev);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        } else {
            // ensure existing admin has developer role
            $admin = Admin::where('email', $email)->first();
            if ($admin) {
                try {
                    $admin->assignRole($dev);
                    $admin->role_id = $dev->id;
                    $admin->save();
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
    }
}
