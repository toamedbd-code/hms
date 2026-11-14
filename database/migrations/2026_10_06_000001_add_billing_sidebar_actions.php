<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $billingMenu = DB::table('menus')
            ->whereNull('parent_id')
            ->where(function ($query) {
                $query->where('route', 'backend.billing.Page')
                    ->orWhere('name', 'Billing')
                    ->orWhere('permission_name', 'billing');
            })
            ->first();

        if (!$billingMenu) {
            $billingMenuId = DB::table('menus')->insertGetId([
                'name' => 'Billing',
                'icon' => 'billing',
                'route' => 'backend.billing.Page',
                'description' => null,
                'sorting' => 1,
                'parent_id' => null,
                'permission_name' => 'billing',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $billingMenu = (object) ['id' => $billingMenuId];
        }

        $now = now();
        $children = [
            [
                'name' => 'Billing Overview',
                'icon' => 'file-text',
                'route' => 'backend.billing.Page',
                'sorting' => 1,
            ],
            [
                'name' => 'Add Bill',
                'icon' => 'plus-circle',
                'route' => 'backend.billing.view',
                'sorting' => 2,
            ],
        ];

        foreach ($children as $child) {
            DB::table('menus')->updateOrInsert(
                [
                    'parent_id' => $billingMenu->id,
                    'route' => $child['route'],
                ],
                [
                    'name' => $child['name'],
                    'icon' => $child['icon'],
                    'description' => null,
                    'sorting' => $child['sorting'],
                    'permission_name' => 'billing',
                    'status' => 'Active',
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('menus')
            ->where(function ($query) {
                $query->where(function ($childQuery) {
                    $childQuery->where('name', 'Billing Overview')
                        ->where('route', 'backend.billing.Page');
                })->orWhere(function ($childQuery) {
                    $childQuery->where('name', 'Add Bill')
                        ->where('route', 'backend.billing.view');
                });
            })
            ->delete();
    }
};
