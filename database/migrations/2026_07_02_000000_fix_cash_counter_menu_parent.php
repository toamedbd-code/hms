<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')
            ->where('route', 'backend.cash-counter.index')
            ->update(['parent_id' => null]);
    }

    public function down(): void
    {
        // This migration only fixes the cash counter parent relationship.
        // No automatic rollback is defined because the previous parent is not reliably known.
    }
};
