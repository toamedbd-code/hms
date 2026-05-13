<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;

class SalarySheetMenuSeeder extends Seeder
{
    public function run()
    {
        // Find Payroll parent menu by name or permission
        $parent = Menu::where('name', 'Payroll')
            ->orWhere('permission_name', 'payroll-management')
            ->first();

        if (! $parent) {
            // create parent if not found
            $parent = new Menu();
            $parent->name = 'Payroll';
            $parent->icon = 'payroll';
            $parent->route = null;
            $parent->permission_name = 'payroll-management';
            $parent->status = 'Active';
            $parent->sorting = 999;
            $parent->save();
        }

        $children = [
            [
                'route' => 'backend.staffattendance.salary-sheet',
                'name' => 'Salary Sheet',
                'icon' => 'file-text',
                'permission_name' => 'salary-sheet',
                'sorting' => 4,
            ],
            [
                'route' => 'backend.staffattendance.duty-roster',
                'name' => 'Duty Roster',
                'icon' => 'calendar',
                'permission_name' => 'dutyroaster-list',
                'sorting' => 5,
            ],
        ];

        foreach ($children as $child) {
            $menu = Menu::query()->where('route', $child['route'])->first();
            if (! $menu) {
                $menu = new Menu();
            }

            $menu->name = $child['name'];
            $menu->icon = $child['icon'];
            $menu->route = $child['route'];
            $menu->permission_name = $child['permission_name'];
            $menu->status = 'Active';
            $menu->sorting = $child['sorting'];
            $menu->parent_id = $parent->id;
            $menu->save();
        }
    }
}
