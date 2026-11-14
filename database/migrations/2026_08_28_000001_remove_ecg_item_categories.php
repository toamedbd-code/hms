<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('testcategories')
            ->whereIn('name', ['ECG', 'ECHO & ECG'])
            ->delete();
    }

    public function down(): void
    {
        // ECG item categories are intentionally not restored.
    }
};