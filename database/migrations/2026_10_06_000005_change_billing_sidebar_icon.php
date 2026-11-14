<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')
            ->where('route', 'backend.billing.view')
            ->whereNull('parent_id')
            ->update([
                'icon' => 'credit-card',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('menus')
            ->where('route', 'backend.billing.view')
            ->whereNull('parent_id')
            ->update([
                'icon' => 'plus-circle',
                'updated_at' => now(),
            ]);
    }
};
