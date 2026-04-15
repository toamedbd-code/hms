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
        Schema::table('bill_items', function (Blueprint $table) {
            if (!Schema::hasColumn('bill_items', 'sample_collected_at')) {
                $table->dateTime('sample_collected_at')->nullable()->after('net_amount');
            }
            if (!Schema::hasColumn('bill_items', 'reported_at')) {
                $table->dateTime('reported_at')->nullable()->after('sample_collected_at');
            }
            if (!Schema::hasColumn('bill_items', 'report_note')) {
                $table->text('report_note')->nullable()->after('reported_at');
            }
            if (!Schema::hasColumn('bill_items', 'report_file')) {
                $table->string('report_file')->nullable()->after('report_note');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            if (Schema::hasColumn('bill_items', 'report_file')) {
                $table->dropColumn('report_file');
            }
            if (Schema::hasColumn('bill_items', 'report_note')) {
                $table->dropColumn('report_note');
            }
            if (Schema::hasColumn('bill_items', 'reported_at')) {
                $table->dropColumn('reported_at');
            }
            if (Schema::hasColumn('bill_items', 'sample_collected_at')) {
                $table->dropColumn('sample_collected_at');
            }
        });
    }
};
