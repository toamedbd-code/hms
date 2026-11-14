<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')
            ->where('route', 'backend.billing.Page')
            ->whereNull('parent_id')
            ->update([
                'name' => 'Module Billing',
                'updated_at' => now(),
            ]);

        DB::table('menus')
            ->where('route', 'backend.billing.view')
            ->whereNull('parent_id')
            ->update([
                'name' => 'Billing',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('menus')
            ->where('route', 'backend.billing.Page')
            ->whereNull('parent_id')
            ->update([
                'name' => 'Billing',
                'updated_at' => now(),
            ]);

        DB::table('menus')
            ->where('route', 'backend.billing.view')
            ->whereNull('parent_id')
            ->update([
                'name' => 'Add Bill',
                'updated_at' => now(),
            ]);
    }
};
