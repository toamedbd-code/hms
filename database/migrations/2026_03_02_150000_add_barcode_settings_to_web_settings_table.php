<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('web_settings', 'barcode_scale')) {
                $table->decimal('barcode_scale', 5, 2)->default(2.20)->after('report_title');
            }
            if (!Schema::hasColumn('web_settings', 'barcode_height')) {
                $table->unsignedSmallInteger('barcode_height')->default(52)->after('barcode_scale');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (Schema::hasColumn('web_settings', 'barcode_height')) {
                $table->dropColumn('barcode_height');
            }
            if (Schema::hasColumn('web_settings', 'barcode_scale')) {
                $table->dropColumn('barcode_scale');
            }
        });
    }
};
