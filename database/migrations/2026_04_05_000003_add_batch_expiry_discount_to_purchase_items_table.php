<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_items', 'batch_no')) {
                $table->string('batch_no', 100)->nullable()->after('medicine_name');
            }

            if (!Schema::hasColumn('purchase_items', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('batch_no');
            }

            if (!Schema::hasColumn('purchase_items', 'discount')) {
                $table->decimal('discount', 11, 2)->default(0)->after('unit_selling_price');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_items', 'discount')) {
                $table->dropColumn('discount');
            }

            if (Schema::hasColumn('purchase_items', 'expiry_date')) {
                $table->dropColumn('expiry_date');
            }

            if (Schema::hasColumn('purchase_items', 'batch_no')) {
                $table->dropColumn('batch_no');
            }
        });
    }
};
