<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments') && !Schema::hasColumn('payments', 'pharmacy_bill_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('pharmacy_bill_id')->nullable()->after('billing_id');
                $table->foreign('pharmacy_bill_id')->references('id')->on('pharmacybills')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'pharmacy_bill_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['pharmacy_bill_id']);
                $table->dropColumn('pharmacy_bill_id');
            });
        }
    }
};
