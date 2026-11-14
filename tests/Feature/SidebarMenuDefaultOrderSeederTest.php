<?php

namespace Tests\Feature;

use App\Models\Menu;
use Database\Seeders\SidebarMenuDefaultOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarMenuDefaultOrderSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_the_saved_sidebar_order_and_appends_unlisted_menus(): void
    {
        $billing = Menu::query()->create([
            'name' => 'Billing',
            'route' => 'backend.billing.view',
            'sorting' => 1,
            'status' => 'Active',
        ]);
        $dashboard = Menu::query()->create([
            'name' => 'Dashboard',
            'route' => 'backend.dashboard',
            'sorting' => 2,
            'status' => 'Active',
        ]);
        $itemCharge = Menu::query()->create([
            'name' => 'Item Charge',
            'route' => 'backend.itemcharge.index',
            'sorting' => 3,
            'status' => 'Active',
        ]);
        $extra = Menu::query()->create([
            'name' => 'New Menu',
            'route' => 'backend.new-menu.index',
            'sorting' => 4,
            'status' => 'Active',
        ]);

        app(SidebarMenuDefaultOrderSeeder::class)->run();

        $this->assertSame(1, (int) $dashboard->fresh()->sorting);
        $this->assertSame(2, (int) $billing->fresh()->sorting);
        $this->assertSame(3, (int) $itemCharge->fresh()->sorting);
        $lastDefaultMenuPosition = Menu::query()
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->whereIn('name', [
                'Dashboard',
                'Billing',
                'Item Charge',
                'Expense Manage',
                'Referral',
                'Reports',
                'Sample Collection',
                'Reporting',
                'Appoinment',
                'Module Billing',
                'IPD - In Patient',
                'Bed Manage',
                'OPD - Out Patient',
                'Prescription',
                'Pharmacy',
                'Pathology',
                'Radiology',
                'Patient',
                'Account Management',
                'Human Resource',
                'Role Management',
                'Settings',
                'Ambulance',
                'Visitor List',
                'Birth & Death Record Certificate',
                'Certificate',
                'TPA Management',
                'BloodIssue Manage',
                'BloodComponentIssue Manage',
                'Inventory',
                'Manufacturing',
                'Fixed Assets',
                'Pharmacy Management',
                'Site Purchase',
                'Vendor Payment',
                'Payroll',
                'Store Management',
                'Lab Management',
                'Cash Counter',
                'Website Inbox',
                'Doctor Portal',
            ])
            ->max('sorting');
        $this->assertGreaterThan($lastDefaultMenuPosition, (int) $extra->fresh()->sorting);
    }
}
