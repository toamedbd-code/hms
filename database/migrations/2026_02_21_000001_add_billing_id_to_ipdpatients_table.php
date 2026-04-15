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
        Schema::table('ipdpatients', function (Blueprint $table) {
            if (!Schema::hasColumn('ipdpatients', 'billing_id')) {
                $table->unsignedBigInteger('billing_id')->nullable()->after('patient_id');
                $table->index(['billing_id']);

                $table->foreign('billing_id')
                    ->references('id')
                    ->on('billings')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ipdpatients', function (Blueprint $table) {
            if (Schema::hasColumn('ipdpatients', 'billing_id')) {
                // Drop foreign key if exists (Laravel will auto-name it, but we can't reliably know the name here).
                // So we try both the conventional name and fallback silently.
                try {
                    $table->dropForeign(['billing_id']);
                } catch (\Throwable $e) {
                    // ignore
                }

                try {
                    $table->dropIndex(['billing_id']);
                } catch (\Throwable $e) {
                    // ignore
                }

                $table->dropColumn('billing_id');
            }
        });
    }
};
