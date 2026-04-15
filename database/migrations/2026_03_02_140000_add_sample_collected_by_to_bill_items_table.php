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
            if (!Schema::hasColumn('bill_items', 'sample_collected_by')) {
                $table->unsignedBigInteger('sample_collected_by')->nullable()->after('sample_collected_at');
                $table->index(['sample_collected_by']);
                $table->foreign('sample_collected_by')
                    ->references('id')
                    ->on('admins')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            if (Schema::hasColumn('bill_items', 'sample_collected_by')) {
                try {
                    $table->dropForeign(['sample_collected_by']);
                } catch (\Throwable $e) {
                    // ignore
                }

                try {
                    $table->dropIndex(['sample_collected_by']);
                } catch (\Throwable $e) {
                    // ignore
                }

                $table->dropColumn('sample_collected_by');
            }
        });
    }
};
