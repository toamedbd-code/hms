<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentAccountToSupplierPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('supplier_payments')) {
            return;
        }

        Schema::table('supplier_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('supplier_payments', 'payment_account_id')) {
                $table->unsignedBigInteger('payment_account_id')->nullable()->after('supplier_id');
                $table->foreign('payment_account_id')
                    ->references('id')
                    ->on('accounts')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('supplier_payments')) {
            return;
        }

        Schema::table('supplier_payments', function (Blueprint $table) {
            if (Schema::hasColumn('supplier_payments', 'payment_account_id')) {
                $table->dropForeign(['payment_account_id']);
                $table->dropColumn('payment_account_id');
            }
        });
    }
}
