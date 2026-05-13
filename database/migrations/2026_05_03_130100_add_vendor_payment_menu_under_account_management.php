<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddVendorPaymentMenuUnderAccountManagement extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        $parentMenu = DB::table('menus')
            ->whereNull('parent_id')
            ->where('permission_name', 'account-management')
            ->first();

        $parentMenuId = $parentMenu->id ?? null;

        $existingMenu = DB::table('menus')
            ->where('route', 'backend.accounts.vendor-payment.index')
            ->first();

        if (!$existingMenu) {
            DB::table('menus')->insert([
                'name' => 'Vendor Payment',
                'icon' => 'list',
                'route' => 'backend.accounts.vendor-payment.index',
                'module_slug' => 'accounting',
                'description' => 'Pay vendor due from account',
                'sorting' => 2,
                'parent_id' => $parentMenuId,
                'permission_name' => 'supplier-payment-list',
                'status' => 'Active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menus')->where('route', 'backend.accounts.vendor-payment.index')->delete();
    }
}
