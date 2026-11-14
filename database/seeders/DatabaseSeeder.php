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
        if (config('app.single_developer_seed', true)) {
            $this->call([
                SingleDeveloperSeeder::class,
                EnsureDefaultDeveloperSeeder::class,
                OrgStructureSeeder::class,
                DashboardCategoryPermissionSeeder::class,
                ChartOfAccountsSeeder::class,
                CurrencySeeder::class,
                PharmacyPrescriptionModuleSeeder::class,
                SidebarMenuDefaultOrderSeeder::class,
            ]);

            return;
        }

        // Full seed (legacy)
        $this->call([
            MenuSeeder::class,
            UpdateItemChargeMenuSeeder::class,
            SettingsMenuSyncSeeder::class,
            QuickAccessMenuPermissionSyncSeeder::class,
            StoreManagementMenuSeeder::class,
            SalarySheetMenuSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            LaboratoryMenuSeeder::class,
            PermissionFixSeeder::class,
            MenuPermissionCoverageSeeder::class,
            ReportManagementPermissionSeeder::class,
            DutyRosterPermissionSeeder::class,
            SalarySheetPermissionSeeder::class,

            CompanySeeder::class,
            OrgStructureSeeder::class,

            AdminSeeder::class,

            DepartmentSeeder::class,
            DesignationSeeder::class,
            SpecialistSeeder::class,

            PatientSeeder::class,

            ChargeSystemSeeder::class,
            PathologySeeder::class,
            CategoryStructureSeeder::class,
            TestSeeder::class,
            DashboardCategoryPermissionSeeder::class,
            MedicineSeeder::class,
            WebSettingSeeder::class,
            AttendanceSeeder::class,
            ChartOfAccountsSeeder::class,
            CurrencySeeder::class,
            EnsureDefaultDeveloperSeeder::class,
            SidebarPermissionStabilitySeeder::class,
            SidebarMenuOrderPermissionSeeder::class,
            PharmacyPrescriptionModuleSeeder::class,
            SettingsMenuAccessForAllRolesSeeder::class,
            // Run deduplication after every menu/permission sync has finished.
            MenuDeduplicateSeeder::class,
            SidebarMenuDefaultOrderSeeder::class,

        ]);
    }
}
