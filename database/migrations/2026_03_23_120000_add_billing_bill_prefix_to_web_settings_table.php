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
            if (!Schema::hasColumn('web_settings', 'billing_bill_prefix')) {
                $table->string('billing_bill_prefix', 20)
                    ->default('BILL')
                    ->after('pharmacy_bill_prefix');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            if (Schema::hasColumn('web_settings', 'billing_bill_prefix')) {
                $table->dropColumn('billing_bill_prefix');
            }
        });
    }
};
