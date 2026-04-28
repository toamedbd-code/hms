<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\SalaryAllowanceType;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Default behavior for fresh databases: create a single developer account
        // with all permissions. Set SINGLE_DEVELOPER_SEED=false in your .env
        // if you want the original full seeding behavior.
        if (env('SINGLE_DEVELOPER_SEED', true)) {
            $this->call([
                SingleDeveloperSeeder::class,
            ]);

            return;
        }

        // Full seed (legacy)
        $this->call([
            MenuSeeder::class,
            SettingsMenuSyncSeeder::class,
            QuickAccessMenuPermissionSyncSeeder::class,
            StoreManagementMenuSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            PermissionFixSeeder::class,
            MenuPermissionCoverageSeeder::class,
            DutyRosterPermissionSeeder::class,
            SalarySheetPermissionSeeder::class,

            CompanySeeder::class,

            AdminSeeder::class,

            DepartmentSeeder::class,
            DesignationSeeder::class,
            SpecialistSeeder::class,

            PatientSeeder::class,

            ChargeSystemSeeder::class,
            PathologySeeder::class,
            TestSeeder::class,
            MedicineSeeder::class,
            WebSettingSeeder::class,
            AttendanceSeeder::class,
                SalarySheetMenuSeeder::class,
            ChartOfAccountsSeeder::class,

        ]);
    }
}
