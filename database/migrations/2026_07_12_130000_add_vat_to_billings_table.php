<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            if (!Schema::hasColumn('billings', 'vat_percentage')) {
                $table->decimal('vat_percentage', 5, 2)->default(0.00)->after('discount_type');
            }
            if (!Schema::hasColumn('billings', 'vat_amount')) {
                $table->decimal('vat_amount', 10, 2)->default(0.00)->after('vat_percentage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            if (Schema::hasColumn('billings', 'vat_amount')) {
                $table->dropColumn('vat_amount');
            }
            if (Schema::hasColumn('billings', 'vat_percentage')) {
                $table->dropColumn('vat_percentage');
            }
        });
    }
};
