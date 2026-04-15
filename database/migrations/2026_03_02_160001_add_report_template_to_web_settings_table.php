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
            if (!Schema::hasColumn('web_settings', 'report_header_html')) {
                $table->text('report_header_html')->nullable()->after('barcode_height');
            }
            if (!Schema::hasColumn('web_settings', 'report_footer_html')) {
                $table->text('report_footer_html')->nullable()->after('report_header_html');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (Schema::hasColumn('web_settings', 'report_footer_html')) {
                $table->dropColumn('report_footer_html');
            }
            if (Schema::hasColumn('web_settings', 'report_header_html')) {
                $table->dropColumn('report_header_html');
            }
        });
    }
};
