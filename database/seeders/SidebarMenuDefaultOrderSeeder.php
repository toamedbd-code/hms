<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class SidebarMenuDefaultOrderSeeder extends Seeder
{
    public function run(): void
    {
        $defaultOrder = [
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
        ];
        $positions = array_flip(array_map(
            static fn (string $name): string => mb_strtolower($name),
            $defaultOrder
        ));

        $menus = Menu::query()
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->where('status', 'Active')
            ->orderBy('sorting')
            ->orderBy('id')
            ->get();

        $orderedMenus = collect();
        foreach ($defaultOrder as $name) {
            $menu = $menus->first(
                static fn (Menu $item): bool => mb_strtolower(trim((string) $item->name)) === mb_strtolower($name)
            );
            if ($menu) {
                $orderedMenus->push($menu);
            }
        }

        $orderedMenus = $orderedMenus->concat($menus->reject(
            static fn (Menu $item): bool => isset($positions[mb_strtolower(trim((string) $item->name))])
        ));

        foreach ($orderedMenus->values() as $index => $menu) {
            $sorting = $index + 1;
            if ((int) $menu->sorting !== $sorting) {
                Menu::query()->whereKey($menu->id)->update(['sorting' => $sorting]);
            }
        }
    }
}
