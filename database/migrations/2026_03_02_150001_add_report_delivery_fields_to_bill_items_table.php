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
            if (!Schema::hasColumn('bill_items', 'reported_by')) {
                $table->unsignedBigInteger('reported_by')->nullable()->after('reported_at');
                $table->index(['reported_by']);
                $table->foreign('reported_by')
                    ->references('id')
                    ->on('admins')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('bill_items', 'sent_at')) {
                $table->dateTime('sent_at')->nullable()->after('reported_by');
            }

            if (!Schema::hasColumn('bill_items', 'sent_via')) {
                $table->string('sent_via', 50)->nullable()->after('sent_at');
            }

            if (!Schema::hasColumn('bill_items', 'delivered_at')) {
                $table->dateTime('delivered_at')->nullable()->after('sent_via');
            }

            if (!Schema::hasColumn('bill_items', 'delivered_by')) {
                $table->unsignedBigInteger('delivered_by')->nullable()->after('delivered_at');
                $table->index(['delivered_by']);
                $table->foreign('delivered_by')
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
            if (Schema::hasColumn('bill_items', 'delivered_by')) {
                try {
                    $table->dropForeign(['delivered_by']);
                } catch (\Throwable $e) {
                    // ignore
                }

                try {
                    $table->dropIndex(['delivered_by']);
                } catch (\Throwable $e) {
                    // ignore
                }

                $table->dropColumn('delivered_by');
            }

            if (Schema::hasColumn('bill_items', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }

            if (Schema::hasColumn('bill_items', 'sent_via')) {
                $table->dropColumn('sent_via');
            }

            if (Schema::hasColumn('bill_items', 'sent_at')) {
                $table->dropColumn('sent_at');
            }

            if (Schema::hasColumn('bill_items', 'reported_by')) {
                try {
                    $table->dropForeign(['reported_by']);
                } catch (\Throwable $e) {
                    // ignore
                }

                try {
                    $table->dropIndex(['reported_by']);
                } catch (\Throwable $e) {
                    // ignore
                }

                $table->dropColumn('reported_by');
            }
        });
    }
};
