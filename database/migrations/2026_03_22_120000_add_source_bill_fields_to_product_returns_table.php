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
        Schema::table('product_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('product_returns', 'source_bill_no')) {
                $table->string('source_bill_no')->nullable()->after('supplier_id');
            }

            if (!Schema::hasColumn('product_returns', 'billing_id')) {
                $table->unsignedBigInteger('billing_id')->nullable()->after('source_bill_no');
                $table->foreign('billing_id')
                    ->references('id')
                    ->on('billings')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('product_returns', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('billing_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_returns', function (Blueprint $table) {
            if (Schema::hasColumn('product_returns', 'billing_id')) {
                $table->dropForeign(['billing_id']);
                $table->dropColumn('billing_id');
            }

            if (Schema::hasColumn('product_returns', 'source_bill_no')) {
                $table->dropColumn('source_bill_no');
            }

            if (Schema::hasColumn('product_returns', 'customer_name')) {
                $table->dropColumn('customer_name');
            }
        });
    }
};
