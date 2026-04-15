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
            if (!Schema::hasColumn('product_returns', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0)->after('total_amount');
            }

            if (!Schema::hasColumn('product_returns', 'payment_status')) {
                $table->enum('payment_status', ['unpaid', 'partial', 'paid'])
                    ->default('unpaid')
                    ->after('paid_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_returns', function (Blueprint $table) {
            if (Schema::hasColumn('product_returns', 'payment_status')) {
                $table->dropColumn('payment_status');
            }

            if (Schema::hasColumn('product_returns', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }
        });
    }
};
