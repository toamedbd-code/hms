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
            throw new RuntimeException('Cannot move Add Bill to the sidebar because the Billing menu is missing.');
        }

        $addBill = DB::table('menus')
            ->where('route', 'backend.billing.view')
            ->where('name', 'Add Bill')
            ->first();

        $values = [
            'name' => 'Add Bill',
            'icon' => 'plus-circle',
            'description' => null,
            'sorting' => ((int) $billingMenu->sorting) + 1,
            'parent_id' => null,
            'permission_name' => 'billing',
            'status' => 'Active',
            'deleted_at' => null,
            'updated_at' => now(),
        ];

        if ($addBill) {
            DB::table('menus')->where('id', $addBill->id)->update($values);
        } else {
            DB::table('menus')->insert($values + [
                'route' => 'backend.billing.view',
                'created_at' => now(),
            ]);
        }

        DB::table('menus')
            ->where('parent_id', $billingMenu->id)
            ->where('name', 'Billing Overview')
            ->where('route', 'backend.billing.Page')
            ->delete();
    }

    public function down(): void
    {
        $billingMenu = DB::table('menus')
            ->whereNull('parent_id')
            ->where('route', 'backend.billing.Page')
            ->first();

        if (!$billingMenu) {
            throw new RuntimeException('Cannot restore the Add Bill submenu because the Billing menu is missing.');
        }

        DB::table('menus')
            ->where('route', 'backend.billing.view')
            ->where('name', 'Add Bill')
            ->update([
                'parent_id' => $billingMenu->id,
                'updated_at' => now(),
            ]);

        DB::table('menus')->updateOrInsert(
            [
                'parent_id' => $billingMenu->id,
                'route' => 'backend.billing.Page',
            ],
            [
                'name' => 'Billing Overview',
                'icon' => 'file-text',
                'description' => null,
                'sorting' => 1,
                'permission_name' => 'billing',
                'status' => 'Active',
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};
