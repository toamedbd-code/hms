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
            $parent = Menu::create([
                'name' => 'Payroll',
                'icon' => 'payroll',
                'route' => null,
                'permission_name' => 'payroll-management',
                'status' => 'Active',
                'sorting' => 999,
            ]);
        }

        // check for existing Salary Sheet child
        $exists = Menu::query()
            ->where('parent_id', $parent->id)
            ->where(function ($q) {
                $q->where('route', 'backend.staffattendance.salary-sheet')
                  ->orWhere('name', 'Salary Sheet');
            })
            ->exists();

        if (! $exists) {
            $maxSort = Menu::where('parent_id', $parent->id)->max('sorting') ?? 0;
            Menu::create([
                'name' => 'Salary Sheet',
                'icon' => 'file-text',
                'route' => 'backend.staffattendance.salary-sheet',
                'permission_name' => 'staff-attendance-list',
                'status' => 'Active',
                'sorting' => $maxSort + 1,
                'parent_id' => $parent->id,
            ]);
        }
    }
}
