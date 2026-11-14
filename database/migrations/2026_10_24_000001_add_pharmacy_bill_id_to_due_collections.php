<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('due_collections', 'pharmacy_bill_id')) {
            Schema::table('due_collections', function (Blueprint $table) {
                $table->unsignedBigInteger('pharmacy_bill_id')->nullable()->after('billing_id');
                $table->foreign('pharmacy_bill_id')
                    ->references('id')
                    ->on('pharmacybills')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('due_collections', 'pharmacy_bill_id')) {
            Schema::table('due_collections', function (Blueprint $table) {
                $table->dropForeign(['pharmacy_bill_id']);
                $table->dropColumn('pharmacy_bill_id');
            });
        }
    }
};
