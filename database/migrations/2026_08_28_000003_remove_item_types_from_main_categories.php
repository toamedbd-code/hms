<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('main_categories')
            ->whereIn(DB::raw('LOWER(name)'), [
                'ambulance',
                'appointment',
                'blood bank',
                'disposable',
                'ipd',
                'opd',
                'pathology',
                'radiology',
                'ultrasound',
            ])
            ->delete();
    }

    public function down(): void
    {
        // Item Category types are intentionally not restored as report headings.
    }
};