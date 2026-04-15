<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('web_settings', 'low_stock_threshold')) {
                $table->unsignedInteger('low_stock_threshold')->default(10);
            }
        });
    }

    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (Schema::hasColumn('web_settings', 'low_stock_threshold')) {
                $table->dropColumn('low_stock_threshold');
            }
        });
    }
};
