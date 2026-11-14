<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bill_items') && Schema::hasColumn('bill_items', 'category')) {
            Schema::table('bill_items', function (Blueprint $table) {
                $table->string('category', 100)->change();
            });
        }
    }

    public function down(): void
    {
        // Keep the category column flexible so custom categories remain valid.
    }
};
